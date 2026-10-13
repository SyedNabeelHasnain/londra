<?php
declare(strict_types=1);
namespace Tests\Feature;
use App\Jobs\SendInquiryEmail;
use App\Actions\SubmitDataRequest;
use App\Jobs\SendPrivacyEmail;
use App\Models\{Inquiry,NotificationDelivery,Setting};
use App\Services\{DataRequestReview,InquirySmtpSender};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\PortalFixtures;
use Tests\TestCase;
final class DeliveryTest extends TestCase
{
    use RefreshDatabase,PortalFixtures;
    protected function setUp(): void { parent::setUp();$this->seed();config(['londra.send_notifications'=>false]); }
    public function test_disabled_smtp_keeps_a_pending_delivery_without_sending(): void
    {
        $transport=$this->fakeEmailTransport();config(['londra.send_notifications'=>false]);
        $inquiry=Inquiry::create(['reference'=>'LT-SYNTHETIC','submission_key'=>str_repeat('1',64),'payload_fingerprint'=>str_repeat('2',64),'type'=>'booking','locale'=>'es','name'=>'Synthetic Test','email'=>'client@example.test','email_lookup'=>str_repeat('3',64),'phone'=>'+440000000000','privacy_notice_at'=>now(),'privacy_notice_version'=>'test-only']);
        $delivery=NotificationDelivery::create(['inquiry_id'=>$inquiry->id,'kind'=>'customer','status'=>'queued']);
        app()->call([new SendInquiryEmail($delivery->id),'handle']);
        $this->assertSame('pending',$delivery->fresh()->status);$this->assertCount(0,$transport->sent);
    }
    public function test_inquiry_mail_uses_actual_references_recipients_and_localized_customer_copy_once(): void
    {
        $transport=$this->approveSyntheticOperation();
        foreach(['en','it','es'] as $locale){
            $inquiry=$this->syntheticInquiry(['type'=>'booking','locale'=>$locale]);
            foreach(['admin','customer'] as $kind){
                $delivery=$inquiry->deliveries()->create(['kind'=>$kind,'status'=>'queued']);
                $job=new SendInquiryEmail($delivery->id);$job->handle(app(InquirySmtpSender::class));
                $sent=$transport->sent[array_key_last($transport->sent)];
                $this->assertSame($kind==='admin'?'info@londratransfers.co.uk':'guest@example.test',$sent['recipient']);
                $this->assertSame($kind==='admin'?'guest@example.test':null,$sent['replyTo']);
                $this->assertStringContainsString($inquiry->reference,$sent['subject']);
                $this->assertStringContainsString($inquiry->reference,$sent['plain']);
                $this->assertStringContainsString('lang="'.($kind==='admin'?'en':$locale).'"',$sent['html']);
                $this->assertStringContainsString('info@londratransfers.co.uk',$sent['plain']);
                $this->assertSame('smtp_accepted',$delivery->fresh()->status);$this->assertSame(1,$delivery->fresh()->attempts);
                $count=count($transport->sent);$job->handle(app(InquirySmtpSender::class));$this->assertCount($count,$transport->sent);
            }
        }
        $this->assertCount(6,$transport->sent);
    }
    public function test_transport_failure_preserves_retry_state_and_successful_retry_accepts_once(): void
    {
        $transport=$this->approveSyntheticOperation();$transport->fail=true;
        $inquiry=$this->syntheticInquiry();$delivery=$inquiry->deliveries()->create(['kind'=>'customer','status'=>'queued']);$job=new SendInquiryEmail($delivery->id);
        try{$job->handle(app(InquirySmtpSender::class));$this->fail('Synthetic transport failure was swallowed.');}catch(\RuntimeException $error){$this->assertStringNotContainsString('guest@example.test',$error->getMessage());}
        $this->assertSame('retrying',$delivery->fresh()->status);$this->assertSame(1,$delivery->fresh()->attempts);$this->assertNull($delivery->fresh()->smtp_accepted_at);
        $job->failed(null);$this->assertSame('failed',$delivery->fresh()->status);
        $transport->fail=false;$job->handle(app(InquirySmtpSender::class));
        $this->assertSame('smtp_accepted',$delivery->fresh()->status);$this->assertSame(2,$delivery->fresh()->attempts);
        $job->handle(app(InquirySmtpSender::class));$this->assertCount(1,$transport->sent);
    }
    public function test_changed_smtp_configuration_pauses_both_mail_types_and_reverification_resumes_them(): void
    {
        $transport=$this->approveSyntheticOperation();
        $inquiry=$this->syntheticInquiry();$delivery=$inquiry->deliveries()->create(['kind'=>'customer','status'=>'queued']);
        config(['londra.send_notifications'=>false]);
        $request=app(SubmitDataRequest::class)->handle(['email'=>'guest@example.test','submission_id'=>(string)Str::uuid()],'it','synthetic-privacy-session');
        $privacy=$request->deliveries()->firstOrFail();
        config(['londra.send_notifications'=>true,'mail.mailers.smtp.host'=>'changed-synthetic.example.test']);
        (new SendInquiryEmail($delivery->id))->handle(app(InquirySmtpSender::class));(new SendPrivacyEmail($privacy->id))->handle($transport);
        $this->assertSame('pending',$delivery->fresh()->status);$this->assertSame('pending',$privacy->fresh()->status);
        $this->assertNull($delivery->fresh()->queued_at);$this->assertNull($privacy->fresh()->queued_at);
        $this->assertSame(0,$delivery->fresh()->attempts);$this->assertSame(0,$privacy->fresh()->attempts);$this->assertCount(0,$transport->sent);
        $this->artisan('londra:retry-pending-notifications')->assertSuccessful();$this->artisan('londra:retry-privacy-notifications')->assertSuccessful();$this->assertDatabaseCount('jobs',0);
        $this->recordSyntheticSmtpAcceptance();
        $this->artisan('londra:retry-pending-notifications')->assertSuccessful();$this->artisan('londra:retry-privacy-notifications')->assertSuccessful();$this->assertDatabaseCount('jobs',2);
        $this->artisan('londra:retry-pending-notifications')->assertSuccessful();$this->artisan('londra:retry-privacy-notifications')->assertSuccessful();$this->assertDatabaseCount('jobs',2);
        (new SendInquiryEmail($delivery->id))->handle(app(InquirySmtpSender::class));(new SendPrivacyEmail($privacy->id))->handle($transport);
        $this->assertSame('smtp_accepted',$delivery->fresh()->status);$this->assertSame('smtp_accepted',$privacy->fresh()->status);$this->assertCount(2,$transport->sent);
    }
    public function test_expired_privacy_verification_job_is_cancelled_without_sending(): void
    {
        $transport=$this->approveSyntheticOperation();
        config(['londra.send_notifications'=>false]);
        $request=app(SubmitDataRequest::class)->handle(['email'=>'guest@example.test','submission_id'=>(string)Str::uuid()],'es','synthetic-session');
        $request->update(['verification_expires_at'=>null]);$delivery=$request->deliveries()->firstOrFail();
        config(['londra.send_notifications'=>true]);
        (new SendPrivacyEmail($delivery->id))->handle($transport);
        $this->assertSame('cancelled',$delivery->fresh()->status);$this->assertCount(0,$transport->sent);
        $this->get('/es/privacy-request/verify/'.$request->verification_token)->assertOk();
        $this->post('/es/privacy-request/verify/'.$request->verification_token)->assertRedirect('/es/privacy-request');
        $this->assertNull($request->fresh()->verified_at);$this->assertSame('received',$request->fresh()->status);
    }
    public function test_privacy_retention_decision_keeps_contact_on_failure_then_scrubs_only_after_acceptance(): void
    {
        $transport=$this->approveSyntheticOperation();$user=$this->syntheticAdministrator();
        $request=app(SubmitDataRequest::class)->handle(['email'=>'guest@example.test','submission_id'=>(string)Str::uuid()],'es','synthetic-session');
        $this->post('/es/privacy-request/verify/'.$request->verification_token)->assertStatus(303);
        $reason='Synthetic lawful retention explanation for an isolated test.';
        app(DataRequestReview::class)->refuse($request->fresh(),$user->id,$reason);
        $delivery=$request->deliveries()->where('kind','decision')->firstOrFail();$job=new SendPrivacyEmail($delivery->id);$transport->fail=true;
        try{$job->handle($transport);$this->fail('Synthetic privacy mail failure was swallowed.');}catch(\RuntimeException){}
        $this->assertSame('retrying',$delivery->fresh()->status);$this->assertSame('guest@example.test',$request->fresh()->email);$this->assertSame($reason,$request->fresh()->decision_reason);
        $job->failed(null);$this->assertSame('failed',$delivery->fresh()->status);
        $transport->fail=false;$job->handle($transport);$job->handle($transport);
        $this->assertCount(1,$transport->sent);$this->assertSame('guest@example.test',$transport->sent[0]['recipient']);
        $this->assertStringContainsString($reason,$transport->sent[0]['plain']);$this->assertStringContainsString(trans('ui.privacy_decision_rights',[],'es'),$transport->sent[0]['plain']);
        $this->assertSame('smtp_accepted',$delivery->fresh()->status);$this->assertSame('refused',$request->fresh()->status);
        foreach(['email','email_lookup','verification_token','verification_hash','decision_reason'] as $field){$this->assertNull($request->fresh()->getAttribute($field));}
    }
    public function test_workers_apply_current_cms_mail_copy_and_contact_signatures_without_stale_language_lines(): void
    {
        $transport=$this->approveSyntheticOperation();$overrides=[];
        foreach(['en','it','es'] as $locale){$overrides[$locale]=['email_subject'=>'Synthetic '.$locale.' acknowledgement','email_body'=>'Synthetic '.$locale.' customer message.','privacy_email_subject'=>'Synthetic '.$locale.' privacy subject','privacy_email_verify'=>'Synthetic '.$locale.' verification message.'];}
        $setting=Setting::create(['key'=>'interface-copy','value'=>$overrides]);
        Setting::create(['key'=>'public-contact','value'=>['phone_display'=>'+44 0000 000000','phone_link'=>'+440000000000','email'=>'team@example.test','instagram'=>'synthetic_londra']]);
        app()->setLocale('es');
        foreach(['en','it','es'] as $locale){
            $inquiry=$this->syntheticInquiry(['locale'=>$locale]);app(InquirySmtpSender::class)->send($inquiry,'customer');$sent=$transport->sent[array_key_last($transport->sent)];
            $this->assertStringStartsWith($overrides[$locale]['email_subject'],$sent['subject']);$this->assertStringContainsString($overrides[$locale]['email_body'],$sent['plain']);
            $this->assertStringContainsString('team@example.test',$sent['plain']);$this->assertStringContainsString('+44 0000 000000',$sent['plain']);
            $this->assertStringContainsString('mailto:team@example.test',$sent['html']);$this->assertStringContainsString('tel:+440000000000',$sent['html']);
            $request=app(SubmitDataRequest::class)->handle(['email'=>'guest@example.test','submission_id'=>(string)Str::uuid()],$locale,'synthetic-worker-'.$locale);
            (new SendPrivacyEmail($request->deliveries()->firstOrFail()->id))->handle($transport);$privacy=$transport->sent[array_key_last($transport->sent)];
            $this->assertStringStartsWith($overrides[$locale]['privacy_email_subject'],$privacy['subject']);$this->assertStringContainsString($overrides[$locale]['privacy_email_verify'],$privacy['plain']);
            $this->assertStringContainsString('team@example.test',$privacy['plain']);$this->assertStringContainsString('mailto:team@example.test',$privacy['html']);
            app(InquirySmtpSender::class)->send($inquiry,'admin');$admin=$transport->sent[array_key_last($transport->sent)];
            $this->assertSame('info@londratransfers.co.uk',$admin['recipient']);$this->assertStringContainsString('lang="en"',$admin['html']);$this->assertStringContainsString('team@example.test',$admin['plain']);
        }
        $this->assertCount(9,$transport->sent);$setting->delete();
        $defaults=json_decode(file_get_contents(lang_path('es/ui.json')),true,512,JSON_THROW_ON_ERROR);
        app(InquirySmtpSender::class)->send($this->syntheticInquiry(['locale'=>'es']),'customer');$sent=$transport->sent[array_key_last($transport->sent)];
        $this->assertStringStartsWith($defaults['email_subject'],$sent['subject']);$this->assertStringContainsString($defaults['email_body'],$sent['plain']);$this->assertStringNotContainsString('Synthetic es acknowledgement',$sent['subject']);
        $request=app(SubmitDataRequest::class)->handle(['email'=>'guest@example.test','submission_id'=>(string)Str::uuid()],'es','synthetic-worker-reset');
        (new SendPrivacyEmail($request->deliveries()->firstOrFail()->id))->handle($transport);$sent=$transport->sent[array_key_last($transport->sent)];
        $this->assertStringStartsWith($defaults['privacy_email_subject'],$sent['subject']);$this->assertStringContainsString($defaults['privacy_email_verify'],$sent['plain']);
    }
}
