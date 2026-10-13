<?php
declare(strict_types=1);
namespace Tests\Feature;
use App\Models\User;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\Http\Middleware\EnsureMultiFactorAuthenticationIsEnabled;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Mail,Route};
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;
final class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;
    private User $administrator;
    private string $password;
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
        Mail::fake();
        $this->password=Str::password(32);
        $this->administrator=User::create(['name'=>'Synthetic Administrator','email'=>'admin@example.test','password'=>$this->password]);
        Filament::setCurrentPanel('admin');
        Filament::bootCurrentPanel();
    }
    private function authenticationProvider(): AppAuthentication
    {
        $providers=Filament::getMultiFactorAuthenticationProviders();
        $this->assertCount(1,$providers);
        $this->assertArrayHasKey('app',$providers);
        $this->assertInstanceOf(AppAuthentication::class,$providers['app']);
        return $providers['app'];
    }
    private function passwordLogin(): Testable
    {
        return Livewire::test(Login::class)
            ->fillForm(['email'=>$this->administrator->email,'password'=>$this->password,'remember'=>false],'form')
            ->call('authenticate');
    }
    public function test_panel_keeps_two_factor_optional_and_protects_dashboard_from_guests(): void
    {
        $this->assertFalse(Filament::getCurrentPanel()->isMultiFactorAuthenticationRequired());
        $this->assertTrue($this->authenticationProvider()->isRecoverable());
        $dashboard=app('router')->getRoutes()->getByName('filament.admin.pages.dashboard');
        $this->assertNotNull($dashboard);
        $this->assertNotContains(EnsureMultiFactorAuthenticationIsEnabled::class,$dashboard->gatherMiddleware());
        $this->assertFalse(Route::has('filament.admin.auth.multi-factor-authentication.set-up-required'));
        $this->assertFalse(Route::has('filament.admin.auth.register'));
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk()->assertSee('Sign in');
        $this->assertGuest('web');
    }
    public function test_valid_password_without_two_factor_reaches_dashboard_and_optional_profile_setup(): void
    {
        $this->assertNull($this->administrator->getAppAuthenticationSecret());
        $this->passwordLogin()
            ->assertHasNoErrors()
            ->assertSet('userUndertakingMultiFactorAuthentication',null)
            ->assertRedirect('/admin');
        $this->assertAuthenticatedAs($this->administrator,'web');
        $this->get('/admin')->assertOk()->assertSee('Dashboard');
        $this->get('/admin/profile')->assertOk()->assertSee('Authenticator app')->assertSee('Disabled');
        $this->assertNull($this->administrator->fresh()->getAppAuthenticationSecret());
        Mail::assertNothingSent();
    }
    public function test_wrong_password_and_unknown_email_do_not_authenticate_or_enter_a_two_factor_challenge(): void
    {
        $login=Livewire::test(Login::class)
            ->fillForm(['email'=>$this->administrator->email,'password'=>$this->password.'invalid','remember'=>false],'form')
            ->call('authenticate')
            ->assertHasErrors(['data.email'])
            ->assertSet('userUndertakingMultiFactorAuthentication',null)
            ->assertNoRedirect();
        $this->assertGuest('web');
        $login->fillForm(['email'=>'unknown@example.test','password'=>$this->password,'remember'=>false],'form')
            ->call('authenticate')
            ->assertHasErrors(['data.email'])
            ->assertSet('userUndertakingMultiFactorAuthentication',null)
            ->assertNoRedirect();
        $this->assertGuest('web');
        $this->get('/admin')->assertRedirect('/admin/login');
        Mail::assertNothingSent();
    }
    public function test_configured_two_factor_rejects_an_invalid_code_and_accepts_a_real_authenticator_code(): void
    {
        $provider=$this->authenticationProvider();
        $secret=$provider->generateSecret();
        $provider->saveSecret($this->administrator,$secret);
        $login=$this->passwordLogin()->assertHasNoErrors()->assertNoRedirect();
        $this->assertNotEmpty($login->get('userUndertakingMultiFactorAuthentication'));
        $this->assertGuest('web');
        $this->get('/admin')->assertRedirect('/admin/login');
        $invalidCode='000000';
        while($provider->verifyCode($invalidCode,$secret)){
            $invalidCode=str_pad((string)((int)$invalidCode+1),6,'0',STR_PAD_LEFT);
        }
        $login->fillForm(['app'=>['code'=>$invalidCode]],'multiFactorChallengeForm')
            ->call('authenticate')
            ->assertHasErrors(['data.multiFactor.app.code'])
            ->assertNoRedirect();
        $this->assertGuest('web');
        $login->fillForm(['app'=>['code'=>$provider->getCurrentCode($this->administrator)]],'multiFactorChallengeForm')
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect('/admin');
        $this->assertAuthenticatedAs($this->administrator,'web');
        $this->get('/admin')->assertOk()->assertSee('Dashboard');
        $this->assertSame($secret,$this->administrator->fresh()->getAppAuthenticationSecret());
        Mail::assertNothingSent();
    }
    public function test_optional_two_factor_keeps_recovery_login_and_consumes_the_used_recovery_code(): void
    {
        $provider=$this->authenticationProvider();
        $provider->saveSecret($this->administrator,$provider->generateSecret());
        $recoveryCodes=$provider->generateRecoveryCodes();
        $provider->saveRecoveryCodes($this->administrator,$recoveryCodes);
        $login=$this->passwordLogin()->assertHasNoErrors()->assertNoRedirect();
        $this->assertNotEmpty($login->get('userUndertakingMultiFactorAuthentication'));
        $this->assertGuest('web');
        $login->fillForm(['app'=>['useRecoveryCode'=>true,'recoveryCode'=>$recoveryCodes[0]]],'multiFactorChallengeForm')
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect('/admin');
        $this->assertAuthenticatedAs($this->administrator,'web');
        $this->assertCount(count($recoveryCodes)-1,$this->administrator->fresh()->getAppAuthenticationRecoveryCodes());
        $this->assertFalse($provider->verifyRecoveryCode($recoveryCodes[0],$this->administrator->fresh()));
        $this->get('/admin')->assertOk()->assertSee('Dashboard');
        Mail::assertNothingSent();
    }
}
