<?php
declare(strict_types=1);
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
final class PrivacyAdminTest extends TestCase
{
    use RefreshDatabase;
    public function test_single_administrator_can_open_privacy_request_resource():void
    {
        $this->seed();
        $this->withoutVite();
        $user=User::create(['name'=>'Synthetic administrator','email'=>'admin-privacy@example.test','password'=>'test-password-only']);
        $response=$this->actingAs($user)->get('/admin/privacy-requests');
        $response->assertOk();
        $response->assertSee('Privacy requests');
    }
    public function test_administrator_can_review_release_readiness_and_privacy_schema():void
    {
        $this->seed();
        $this->withoutVite();
        $administrator=User::create(['name'=>'Synthetic administrator','email'=>'admin-readiness@example.test','password'=>'test-password-only']);
        $this->get('/admin/readiness')->assertRedirect();
        $this->actingAs($administrator)->get('/admin/readiness')->assertOk()
            ->assertSee('Privacy-request schema')
            ->assertSee('Release checks');
    }
}
