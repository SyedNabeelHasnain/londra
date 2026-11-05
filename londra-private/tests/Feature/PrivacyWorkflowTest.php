<?php
declare(strict_types=1);
namespace Tests\Feature;
use App\Actions\SubmitDataRequest;
use App\Actions\SubmitInquiry;
use App\Contracts\EmailTransport;
use App\Jobs\SendInquiryEmail;
use App\Jobs\SendPrivacyEmail;
use App\Models\DataRequest;
use App\Models\Inquiry;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Services\DataRequestReview;
use App\Services\ReleaseUpdater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Support\PortalFixtures;
final class PrivacyWorkflowTest extends TestCase
{
    use RefreshDatabase,PortalFixtures;
    protected function setUp(): void { parent::setUp(); $this->seed(); $this->withoutVite(); config(['londra.preview' => false, 'londra.send_notifications' => false]); }
    private function inquiry(array $extra = []): Inquiry
    {
        return Inquiry::create($extra + ['reference' => 'LT-'.Str::ulid(), 'submission_key' => bin2hex(random_bytes(32)), 'payload_fingerprint' => bin2hex(random_bytes(32)), 'type' => 'contact', 'locale' => 'en', 'status' => 'new', 'name' => 'Synthetic Customer', 'email' => 'client@example.test', 'email_lookup' => hash_hmac('sha256', 'client@example.test', config('app.key')), 'phone' => '+440000000000', 'privacy_notice_at' => now(), 'privacy_notice_version' => 'synthetic']);
    }
    private function administrator(): User { return User::create(['name' => 'Synthetic Administrator', 'email' => 'admin@example.test', 'password' => Str::random(30)]); }
    private function request(): DataRequest { return app(SubmitDataRequest::class)->handle(['email' => 'client@example.test', 'submission_id' => (string) Str::uuid()], 'it', 'synthetic-session'); }
    private function approvePolicies(): void
    {
        foreach (Page::whereIn('key', ['privacy', 'terms', 'refund', 'cookies', 'accessibility'])->with('translations')->get() as $page) {
            foreach ($page->translations as $translation) { $translation->update(['ready' => true]); }
            $page->update(['approved_at' => now(), 'published' => true]);
        }
    }
    public function test_minimal_contact_validation_rejects_message_and_financial_fields(): void
    {
        $form = new \App\Http\Requests\StoreInquiryRequest();
        $good = ['name' => 'Synthetic Client', 'email' => 'client@example.test', 'phone' => '+440000000000', 'type' => 'contact', 'submission_id' => (string) Str::uuid(), 'form_token' => 'synthetic'];
        $this->assertFalse(Validator::make($good, $form->rules())->fails());
        foreach (['subject', 'message', 'bank_account', 'card_number', 'payment'] as $field) { $this->assertTrue(Validator::make($good + [$field => 'must not be collected'], $form->rules())->fails()); }
        $this->approvePolicies();
        $record = app(SubmitInquiry::class)->handle($good + ['subject' => 'ignored', 'message' => 'ignored'], 'en', 'synthetic-session');
        $this->assertNull($record->subject); $this->assertNull($record->message);
        $this->approveSyntheticOperation();
        $page = $this->get('/en/contact')->assertOk()->assertDontSee('name="subject"', false)->assertDontSee('name="message"', false)->assertSee('name="phone"', false);
        preg_match_all('/name="submission_id" value="([^"]+)"/', $page->getContent(), $ids);
        $this->assertCount(2, array_unique($ids[1]));
    }
    public function test_request_is_encrypted_and_idempotent_without_disclosing_matches(): void
    {
        $data = ['email' => 'CLIENT@EXAMPLE.TEST', 'submission_id' => (string) Str::uuid()];
        $a = app(SubmitDataRequest::class)->handle($data, 'en', 'synthetic-session');
        $b = app(SubmitDataRequest::class)->handle($data, 'en', 'synthetic-session');
        $this->assertSame($a->id, $b->id); $this->assertDatabaseCount('data_requests', 1); $this->assertDatabaseCount('privacy_deliveries', 1);
        $raw = DB::table('data_requests')->first();
        $this->assertNotSame('client@example.test', $raw->email); $this->assertNotSame($a->verification_token, $raw->verification_token);
        $this->assertArrayNotHasKey('email', $a->toArray());
        $this->expectException(ValidationException::class);
        app(SubmitDataRequest::class)->handle(['email' => 'changed@example.test', 'submission_id' => $data['submission_id']], 'en', 'synthetic-session');
    }
    public function test_reading_verification_link_does_not_erase_or_verify_records(): void
    {
        $inquiry = $this->inquiry(); $request = $this->request(); $token = $request->verification_token;
        $this->get('/it/privacy-request/verify/'.$token)->assertOk();
        $this->assertNull($request->fresh()->verified_at); $this->assertNotNull($inquiry->fresh());
        $this->post('/it/privacy-request/verify/'.$token)->assertRedirect('/it/privacy-request');
        $this->assertSame('in_review', $request->fresh()->status); $this->assertNotNull($inquiry->fresh());
        $this->assertNull($request->fresh()->verification_token); $this->assertNull($request->fresh()->verification_hash);
        $this->post('/it/privacy-request/verify/'.$token)->assertRedirect('/it/privacy-request');
        $this->assertDatabaseCount('privacy_deliveries', 2);
    }
    public function test_unverified_request_cannot_delete_customer_records(): void
    {
        $this->inquiry(); $request = $this->request(); $user = $this->administrator();
        try { app(DataRequestReview::class)->eraseDatabase($request, $user->id); $this->fail('Unverified request erased records.'); } catch (ValidationException) {}
        $this->assertDatabaseCount('inquiries', 1);
    }
    public function test_erasure_cascades_and_waits_for_external_cleanup_before_acknowledgement(): void
    {
        $user = $this->administrator(); $inquiry = $this->inquiry();
        $inquiry->notes()->create(['body' => 'Synthetic private note', 'user_id' => $user->id]);
        $delivery = $inquiry->deliveries()->create(['kind' => 'customer', 'status' => 'queued']);
        SendInquiryEmail::dispatch($delivery->id)->onConnection('database');
        $request = $this->request(); $request->update(['verified_at' => now(), 'status' => 'in_review']);
        app(DataRequestReview::class)->eraseDatabase($request, $user->id);
        $this->assertDatabaseCount('inquiries', 0); $this->assertDatabaseCount('inquiry_notes', 0); $this->assertDatabaseCount('notification_deliveries', 0); $this->assertDatabaseCount('jobs', 0);
        $this->assertSame('database_erased', $request->fresh()->status);
        try { app(DataRequestReview::class)->complete($request, $user->id, true, false); $this->fail('Acknowledgement queued without backup review.'); } catch (ValidationException) {}
        $this->assertSame(0, $request->deliveries()->where('kind', 'complete')->count());
        app(DataRequestReview::class)->complete($request, $user->id, true, true);
        $ack = $request->deliveries()->where('kind', 'complete')->firstOrFail();
        $transport = new class implements EmailTransport { public array $sent = []; public function send(string $recipient, string $subject, string $html, string $plain, ?string $replyTo = null): void { $this->sent[] = [$recipient, $plain]; } };
        config(['londra.send_notifications' => true]);$this->recordSyntheticSmtpAcceptance();
        (new SendPrivacyEmail($ack->id))->handle($transport);
        $this->assertSame('client@example.test', $transport->sent[0][0]); $this->assertStringContainsString('Abbiamo completato', $transport->sent[0][1]);
        $this->assertNull($request->fresh()->email); $this->assertNull($request->fresh()->email_lookup); $this->assertSame('smtp_accepted', $ack->fresh()->status);
        (new SendPrivacyEmail($ack->id))->handle($transport); $this->assertCount(1, $transport->sent);
        $this->assertDatabaseCount('erasure_events', 1);
    }
    public function test_retention_protects_recent_active_converted_and_held_records(): void
    {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-12 12:00:00 UTC'));
        $old = $this->inquiry(['last_substantive_contact_at' => now()->subDays(181)]);
        $boundary = $this->inquiry(['last_substantive_contact_at' => now()->subDays(180)]);
        $recent = $this->inquiry(['last_substantive_contact_at' => now()->subDays(179)]);
        $converted = $this->inquiry(['last_substantive_contact_at' => now()->subDays(181), 'converted_at' => now()]);
        $hold = $this->inquiry(['last_substantive_contact_at' => now()->subDays(181), 'legal_hold_at' => now(), 'legal_hold_reason' => 'Synthetic legal obligation']);
        $active = $this->inquiry(['last_substantive_contact_at' => now()->subDays(181), 'active_until' => now()->addDays(10), 'active_reason' => 'Synthetic active service discussion']);
        $this->artisan('londra:retention')->assertSuccessful(); $this->assertDatabaseCount('inquiries', 6);
        Setting::create(['key' => 'privacy-operations', 'value' => ['retention_days' => 180, 'retention_enabled' => true, 'retention_approved' => true, 'mailbox_process_confirmed' => true, 'backup_process_confirmed' => true]]);
        $this->artisan('londra:retention', ['--dry-run' => true])->assertSuccessful(); $this->assertDatabaseCount('inquiries', 6);
        $this->artisan('londra:retention')->assertSuccessful();
        $this->assertNull($old->fresh()); $this->assertNull($boundary->fresh());
        foreach ([$recent, $converted, $hold, $active] as $record) { $this->assertNotNull($record->fresh()); }
        $this->assertDatabaseCount('erasure_events', 2);
    }
    public function test_legal_hold_stops_an_all_records_deletion_transaction(): void
    {
        $this->inquiry(); $this->inquiry(['legal_hold_at' => now(), 'legal_hold_reason' => 'Synthetic obligation']);
        $request = $this->request(); $request->update(['verified_at' => now(), 'status' => 'in_review']); $user = $this->administrator();
        try { app(DataRequestReview::class)->eraseDatabase($request, $user->id); $this->fail('Protected record erased.'); } catch (ValidationException) {}
        $this->assertDatabaseCount('inquiries', 2); $this->assertSame('in_review', $request->fresh()->status);
    }
    public function test_upgrade_retains_records_and_only_imports_this_policy_revision_once(): void
    {
        $user = $this->administrator(); $inquiry = $this->inquiry(); $encrypted = DB::table('inquiries')->value('email');
        $originalKey = config('app.key'); $smtp = config('mail.mailers.smtp');
        app(ReleaseUpdater::class)->run();
        $this->assertDatabaseCount('users', 1); $this->assertSame($user->id, User::first()->id); $this->assertNotNull($inquiry->fresh());
        $this->assertSame($encrypted, DB::table('inquiries')->value('email')); $this->assertSame($originalKey, config('app.key')); $this->assertSame($smtp, config('mail.mailers.smtp'));
        $privacy = Page::with('translations')->where('key', 'privacy')->firstOrFail();
        $this->assertStringContainsString('14856485', $privacy->translation('en')->body); $this->assertFalse($privacy->published);
        $privacy->translation('en')->update(['intro' => 'Synthetic owner edit.']);
        app(ReleaseUpdater::class)->run();
        $this->assertSame('Synthetic owner edit.', $privacy->fresh()->translations()->where('locale', 'en')->first()->intro);
    }
    public function test_three_public_privacy_pages_and_admin_authentication(): void
    {
        foreach (['en', 'it', 'es'] as $locale) { $this->get('/'.$locale.'/privacy-request')->assertOk()->assertSee('lang="'.$locale.'"', false); }
        $this->get('/admin/data-requests')->assertRedirect();
        $user = $this->administrator();
        $this->actingAs($user)->get('/admin/data-requests')->assertOk();
        $this->actingAs($user)->get('/admin/privacy-settings')->assertOk();
        $this->actingAs($user)->get('/admin/release-update')->assertOk();
        $this->actingAs($user)->get('/admin/launch-settings')->assertOk();
        $this->actingAs($user)->get('/admin/smtp-settings')->assertOk();
        $request = $this->request();
        $this->actingAs($user)->get('/admin/data-requests/'.$request->id.'/edit')->assertOk();
        $inquiry = $this->inquiry();
        $this->actingAs($user)->get('/admin/inquiries/'.$inquiry->id.'/edit')->assertOk();
    }
    public function test_database_sessions_do_not_store_raw_ip_or_user_agent(): void
    {
        $handler = new \App\Services\MinimalDatabaseSessionHandler(DB::connection(), 'sessions', 120, app());
        $handler->write('synthetic-minimal-session', 'synthetic');
        $row = DB::table('sessions')->where('id', 'synthetic-minimal-session')->first();
        $this->assertNull($row->ip_address); $this->assertNull($row->user_agent);
    }
    public function test_browser_language_negotiation_and_explicit_language_precedence(): void
    {
        $this->withHeader('Accept-Language', 'it-IT,it;q=0.9,en;q=0.8')->get('/')->assertRedirect('/it')->assertHeader('Vary', 'Cookie, Accept-Language');
        $this->withHeader('Accept-Language', 'es-ES,es;q=0.9')->get('/')->assertRedirect('/es');
        $this->withHeader('Accept-Language', 'fr-FR,de;q=0.8')->get('/')->assertRedirect('/en');
        $this->get('/')->assertRedirect('/en');
        $this->withHeader('Accept-Language', 'es-ES')->get('/en')->assertOk()->assertSee('lang="en"', false);
        $this->withCookie('londra_language', 'it')->withHeader('Accept-Language', 'es-ES')->get('/')->assertRedirect('/it');
        $this->withCookie('londra_language', 'invalid')->withHeader('Accept-Language', 'es-ES')->get('/')->assertRedirect('/es');
    }
    public function test_the_single_process_cron_reports_real_execution(): void
    {
        $this->artisan('londra:cron')->assertSuccessful();
        $health = Setting::findOrFail('cron-health')->value;
        $this->assertSame(PHP_VERSION, $health['php_version']); $this->assertNotEmpty($health['last_success_at']);
    }
    public function test_all_published_language_pages_render_and_use_existing_images(): void
    {
        foreach (\App\Models\MediaAsset::all() as $asset) { $this->assertFileExists(public_path($asset->public_path)); }
        foreach (Page::published()->with('translations')->get() as $page) {
            foreach ($page->translations as $translation) {
                $this->get($translation->url())->assertOk()->assertSee('lang="'.$translation->locale.'"', false)->assertDontSee('googletagmanager.com')->assertDontSee('google-analytics.com');
            }
        }
    }
    public function test_legacy_unknown_contact_dates_are_not_guessed_or_purged(): void
    {
        $inquiry = $this->inquiry();
        DB::table('inquiries')->where('id', $inquiry->id)->update(['last_substantive_contact_at' => null, 'created_at' => now()->subDays(500)]);
        Setting::create(['key' => 'privacy-operations', 'value' => ['retention_days' => 180, 'retention_enabled' => true, 'retention_approved' => true, 'mailbox_process_confirmed' => true, 'backup_process_confirmed' => true]]);
        $this->artisan('londra:retention')->assertSuccessful();
        $this->assertNotNull($inquiry->fresh()); $this->assertNull($inquiry->fresh()->last_substantive_contact_at);
    }
    public function test_public_submission_succeeds_only_with_approved_retention_and_is_idempotent(): void
    {
        $this->approvePolicies();
        config(['londra.accept_inquiries' => true, 'londra.publication_approved' => true, 'londra.privacy_approved' => true]);
        $this->assertFalse(app(\App\Services\PublicationState::class)->inquiriesAllowed());
        $this->approveSyntheticOperation();
        $sessionId = str_repeat('a', 40);
        $data = ['name' => 'Synthetic Client', 'email' => 'client@example.test', 'phone' => '+440000000000', 'type' => 'contact', 'page_key' => 'contact', 'submission_id' => (string) Str::uuid(), 'form_token' => app(\App\Services\InquiryFormToken::class)->issue($sessionId, 'en')];
        $this->withCredentials()->withCookie(config('session.cookie'), $sessionId)->postJson('/en/inquiries', $data)->assertCreated()->assertJson(['booking_confirmed' => false, 'payment_taken' => false]);
        $this->withCredentials()->withCookie(config('session.cookie'), $sessionId)->postJson('/en/inquiries', $data)->assertOk();
        $this->assertDatabaseCount('inquiries', 1); $this->assertDatabaseCount('notification_deliveries', 2);
    }
    public function test_deletion_request_does_not_erase_a_later_new_enquiry(): void
    {
        $user = $this->administrator(); $old = $this->inquiry(); $request = $this->request(); $later = $this->inquiry();
        $request->update(['status' => 'in_review', 'verified_at' => now()]);
        app(DataRequestReview::class)->eraseDatabase($request, $user->id);
        app(DataRequestReview::class)->complete($request, $user->id, true, true);
        $this->assertNull($old->fresh()); $this->assertNotNull($later->fresh());
        $this->assertSame(1, $request->fresh()->erased_count);
    }
}
