<?php
declare(strict_types=1);
namespace Tests\Feature;
use App\Actions\SubmitInquiry;
use App\Domain\InquiryStatus;
use App\Models\{Inquiry,NotificationDelivery,Page,User};
use App\Services\InquiryFormToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Mail};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
final class PortalTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();$this->seed();$this->withoutVite();Mail::fake();
    }
    public function test_seed_contains_four_confirmed_vehicles_and_no_invented_luggage(): void
    {
        $this->assertSame(['e-class'=>3,'s-class'=>3,'v-class'=>7,'vito'=>8],\App\Models\Vehicle::orderBy('sort_order')->pluck('passengers','code')->all());
        $this->assertDatabaseCount('pages',38);$this->assertDatabaseCount('page_translations',114);
        $this->assertSame(0,Page::where('kind','policy')->where('published',true)->count());
    }
    public function test_policies_and_forms_stay_closed_until_approved(): void
    {
        $this->postJson('/en/inquiries',['type'=>'booking'])->assertForbidden();
        $this->get('/en/privacy')->assertNotFound();
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /',false);
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('/en/services',false);
    }
    public function test_explicit_languages_and_equivalent_links_are_preserved(): void
    {
        $this->get('/it')->assertOk()->assertSee('lang="it"',false)->assertSee('hreflang="es"',false)->assertSee('/language/en/home',false);
        $this->get('/es')->assertOk()->assertSee('lang="es"',false);
        $this->get('/')->assertRedirect('/en');
        $this->get('/language/it/home')->assertRedirect('/it');
        $this->get('/language/it/not-a-page')->assertNotFound();
    }
    public function test_unknown_paths_and_traversal_are_not_public(): void
    {
        $this->get('/en/not-a-page')->assertNotFound();
        $this->get('/media/example.php')->assertNotFound();
        $this->get('/storage/inquiries')->assertNotFound();
    }
    public function test_single_admin_rule_prevents_a_second_administrator(): void
    {
        User::create(['name'=>'Test administrator','email'=>'admin@example.test','password'=>Str::password(24)]);
        $this->expectException(ValidationException::class);
        User::create(['name'=>'Second administrator','email'=>'other@example.test','password'=>Str::password(24)]);
    }
    public function test_form_token_is_bound_to_session_and_language(): void
    {
        $tokens=app(InquiryFormToken::class);$token=$tokens->issue('test-session','it');
        $this->assertTrue($tokens->valid($token,'test-session','it'));
        $this->assertFalse($tokens->valid($token,'other-session','it'));
        $this->assertFalse($tokens->valid($token,'test-session','en'));
        $this->assertFalse($tokens->valid('tampered','test-session','it'));
    }
    public function test_transaction_stores_encrypted_fields_and_two_pending_notifications_once(): void
    {
        $privacy=Page::with('translations')->where('key','privacy')->firstOrFail();
        foreach($privacy->translations as $translation){$translation->update(['intro'=>'Synthetic policy for isolated tests.','body'=>'Not a public business policy.','meta_description'=>'Test policy.','ready'=>true]);}
        $privacy->update(['approved_at'=>now(),'published'=>true]);
        config(['londra.send_notifications'=>false]);
        $data=['submission_id'=>(string)Str::uuid(),'type'=>'booking','name'=>'Synthetic Test','email'=>'client@example.test','phone'=>'+440000000000','subject'=>'','message'=>'','page_key'=>'home','service_key'=>'airport-transfers'];
        $action=app(SubmitInquiry::class);$first=$action->handle($data,'it','test-session');$second=$action->handle($data,'it','test-session');
        $this->assertSame($first->id,$second->id);$this->assertDatabaseCount('inquiries',1);$this->assertDatabaseCount('notification_deliveries',2);
        $stored=DB::table('inquiries')->first();$this->assertNotSame('client@example.test',$stored->email);
        $this->assertSame('client@example.test',Inquiry::firstOrFail()->email);
        $this->assertSame(InquiryStatus::New,$first->status);$this->assertCount(0,DB::table('jobs')->get());
        Mail::assertNothingSent();
    }
    public function test_a_policy_edit_withdraws_previous_publication_approval(): void
    {
        $page=Page::with('translations')->where('key','privacy')->firstOrFail();
        foreach($page->translations as $t){$t->update(['intro'=>'Synthetic test content.','meta_description'=>'Test content.','body'=>'Synthetic content.','ready'=>true]);}
        $page->update(['approved_at'=>now(),'published'=>true]);
        $page->translation('it')->update(['body'=>'Changed synthetic content.']);
        $this->assertFalse($page->fresh()->published);$this->assertNull($page->fresh()->approved_at);
    }
}
