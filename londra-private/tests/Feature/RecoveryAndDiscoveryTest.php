<?php
declare(strict_types=1);
namespace Tests\Feature;
use App\Actions\SubmitDataRequest;
use App\Models\{Inquiry,Page};
use App\Services\{InquiryFormToken,PublicationState};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Crypt,DB};
use Illuminate\Support\Str;
use Tests\Support\PortalFixtures;
use Tests\TestCase;
final class RecoveryAndDiscoveryTest extends TestCase
{
    use RefreshDatabase,PortalFixtures;
    protected function setUp(): void
    {
        parent::setUp();$this->seed();$this->withoutVite();config(['londra.preview'=>false]);
    }
    private function sessionRequest(string $sessionId): static
    {
        return $this->withCredentials()->withCookie(config('session.cookie'),$sessionId);
    }
    private function formPayload(string $token): array
    {
        return ['name'=>'Synthetic Guest','email'=>'guest@example.test','phone'=>'+440000000000','type'=>'contact','page_key'=>'contact','submission_id'=>(string)Str::uuid(),'form_token'=>$token];
    }
    private function staleToken(string $sessionId,string $locale,int $issuedAt): string
    {
        return Crypt::encryptString(json_encode(['session'=>hash('sha256',$sessionId),'locale'=>$locale,'issued_at'=>$issuedAt],JSON_THROW_ON_ERROR));
    }
    public function test_token_refresh_is_closed_until_actual_collection_prerequisites_are_met(): void
    {
        $response=$this->getJson('/en/form-tokens')->assertForbidden();
        $this->assertNull($response->json('form_token'));
        $this->assertNull($response->json('privacy_form_token'));
        $this->assertStringContainsString('no-store',(string)$response->headers->get('Cache-Control'));
        $this->assertStringContainsString('noindex',(string)$response->headers->get('X-Robots-Tag'));
        $this->assertDatabaseCount('inquiries',0);
    }
    public function test_refresh_tokens_are_private_and_bound_to_session_language_and_purpose(): void
    {
        $this->approveSyntheticOperation();
        $sessionId=str_repeat('b',40);
        $response=$this->sessionRequest($sessionId)->getJson('/it/form-tokens')->assertOk()->assertJson(['inquiries_allowed'=>true,'privacy_requests_allowed'=>true]);
        $this->assertNotSame('',(string)$response->json('csrf_token'));
        $tokens=app(InquiryFormToken::class);
        $this->assertTrue($tokens->valid($response->json('form_token'),$sessionId,'it'));
        $this->assertFalse($tokens->valid($response->json('form_token'),'different-session','it'));
        $this->assertFalse($tokens->valid($response->json('form_token'),$sessionId,'en'));
        $this->assertTrue($tokens->valid($response->json('privacy_form_token'),$sessionId,'it:privacy'));
        $this->assertFalse($tokens->valid($response->json('privacy_form_token'),$sessionId,'it'));
        $this->assertStringContainsString('no-store',(string)$response->headers->get('Cache-Control'));
        $this->assertStringContainsString('noindex',(string)$response->headers->get('X-Robots-Tag'));
    }
    public function test_expired_token_can_be_refreshed_without_duplicating_or_changing_the_guest_submission(): void
    {
        $transport=$this->approveSyntheticOperation();
        $sessionId=str_repeat('c',40);
        $expired=$this->staleToken($sessionId,'es',time()-7201);
        $payload=$this->formPayload($expired);
        $this->sessionRequest($sessionId)->postJson('/es/inquiries',$payload)->assertStatus(419)->assertJson(['code'=>'form_expired','message'=>trans('ui.form_expired',[],'es')]);
        $this->assertDatabaseCount('inquiries',0);
        $this->assertDatabaseCount('jobs',0);
        $fresh=$this->sessionRequest($sessionId)->getJson('/es/form-tokens')->assertOk();
        $payload['form_token']=$fresh->json('form_token');
        $first=$this->sessionRequest($sessionId)->postJson('/es/inquiries',$payload)->assertCreated()->assertJson(['booking_confirmed'=>false,'payment_taken'=>false]);
        $again=$this->sessionRequest($sessionId)->postJson('/es/inquiries',$payload)->assertOk();
        $this->assertSame($first->json('reference'),$again->json('reference'));
        $this->assertDatabaseCount('inquiries',1);
        $this->assertDatabaseCount('notification_deliveries',2);
        $this->assertCount(0,$transport->sent);
    }
    public function test_future_tampered_and_wrong_purpose_tokens_cannot_store_enquiries(): void
    {
        $this->approveSyntheticOperation();
        $sessionId=str_repeat('d',40);
        $tokens=app(InquiryFormToken::class);
        foreach([$this->staleToken($sessionId,'en',time()+60),'tampered-token',$tokens->issue($sessionId,'it'),$tokens->issue($sessionId,'en:privacy')] as $token){
            $this->sessionRequest($sessionId)->postJson('/en/inquiries',$this->formPayload($token))->assertStatus(419);
        }
        $this->assertDatabaseCount('inquiries',0);
        $this->assertDatabaseCount('notification_deliveries',0);
    }
    public function test_effective_mail_and_retention_settings_control_collection(): void
    {
        $this->approveSyntheticOperation();
        $state=app(PublicationState::class);
        $this->assertTrue($state->inquiriesAllowed());
        $this->assertTrue($state->privacyRequestsAllowed());
        config(['londra.send_notifications'=>false]);
        $this->assertFalse($state->inquiriesAllowed());
        $this->assertFalse($state->privacyRequestsAllowed());
        config(['londra.send_notifications'=>true,'mail.mailers.smtp.host'=>'changed-synthetic-host.example.test']);
        $this->assertFalse($state->inquiriesAllowed());
        $this->assertFalse($state->privacyRequestsAllowed());
    }
    public function test_privacy_expiry_and_validation_use_their_own_localized_recovery_messages(): void
    {
        $this->approveSyntheticOperation();$sessionId=str_repeat('g',40);
        $token=$this->staleToken($sessionId,'it:privacy',time()-7201);
        $data=['email'=>'guest@example.test','submission_id'=>(string)Str::uuid(),'form_token'=>$token];
        $this->sessionRequest($sessionId)->postJson('/it/privacy-requests',$data)->assertStatus(419)->assertJson(['code'=>'form_expired','message'=>trans('ui.privacy_form_expired',[],'it')]);
        $data['form_token']=app(InquiryFormToken::class)->issue($sessionId,'it:privacy');$data['email']='invalid';
        $this->sessionRequest($sessionId)->postJson('/it/privacy-requests',$data)->assertUnprocessable()->assertJson(['message'=>trans('ui.privacy_try_again',[],'it')])->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('data_requests',0);
    }
    public function test_rate_limited_enquiry_preserves_retry_after_and_does_not_store_data(): void
    {
        $this->approveSyntheticOperation();$sessionId=str_repeat('h',40);
        $token=$this->staleToken($sessionId,'en',time()-7201);
        for($index=0;$index<5;$index++){$this->sessionRequest($sessionId)->postJson('/en/inquiries',$this->formPayload($token))->assertStatus(419);}
        $response=$this->sessionRequest($sessionId)->postJson('/en/inquiries',$this->formPayload($token))->assertStatus(429)->assertJson(['code'=>'rate_limited','message'=>trans('ui.retry_after',[],'en')]);
        $this->assertGreaterThan(0,(int)$response->headers->get('Retry-After'));
        $this->assertStringContainsString('no-store',(string)$response->headers->get('Cache-Control'));
        $this->assertDatabaseCount('inquiries',0);
    }
    public function test_native_receipt_target_is_checked_before_storing_an_enquiry(): void
    {
        $this->approveSyntheticOperation();$sessionId=str_repeat('i',40);
        DB::table('pages')->where('key','contact')->update(['published'=>false]);
        $payload=$this->formPayload(app(InquiryFormToken::class)->issue($sessionId,'en'));
        $this->sessionRequest($sessionId)->post('/en/inquiries',$payload)->assertStatus(503);
        $this->assertDatabaseCount('inquiries',0);$this->assertDatabaseCount('notification_deliveries',0);$this->assertDatabaseCount('jobs',0);
    }
    public function test_native_success_uses_303_and_the_correct_localized_contact_or_journey_receipt(): void
    {
        $this->approveSyntheticOperation();
        foreach(['en','it','es'] as $index=>$locale){
            $this->withServerVariables(['REMOTE_ADDR'=>'192.0.2.'.($index+1)]);$sessionId=str_repeat(chr(106+$index),40);
            $token=app(InquiryFormToken::class)->issue($sessionId,$locale);
            foreach(['contact'=>'contact','booking'=>'enquiry'] as $type=>$key){
                $payload=$this->formPayload($token);$payload['type']=$type;
                $destination=Page::where('key',$key)->firstOrFail()->translation($locale)->url();
                $this->sessionRequest($sessionId)->post('/'.$locale.'/inquiries',$payload)->assertStatus(303)->assertRedirect($destination)->assertSessionHas('inquiry_receipt');
            }
        }
        $this->assertDatabaseCount('inquiries',6);$this->assertDatabaseCount('notification_deliveries',12);
    }
    public function test_native_privacy_receipts_follow_the_current_cms_slug_without_a_redirect_chain(): void
    {
        $this->approveSyntheticOperation();$sessionId=str_repeat('m',40);
        $target=Page::where('key','privacy-request')->firstOrFail()->translation('it');$target->update(['slug'=>'richiesta-privacy-sintetica']);
        $payload=['email'=>'guest@example.test','submission_id'=>(string)Str::uuid(),'form_token'=>app(InquiryFormToken::class)->issue($sessionId,'it:privacy')];
        $this->sessionRequest($sessionId)->post('/it/privacy-requests',$payload)->assertStatus(303)->assertRedirect($target->url())->assertSessionHas('privacy_receipt');
        $request=\App\Models\DataRequest::firstOrFail();
        $this->sessionRequest($sessionId)->post('/it/privacy-request/verify/'.$request->verification_token)->assertStatus(303)->assertRedirect($target->url())->assertSessionHas('privacy_receipt');
        $this->assertSame('in_review',$request->fresh()->status);$this->assertNotNull($request->fresh()->verified_at);
    }
    public function test_native_expired_forms_use_safe_current_localized_recovery_targets_and_ignore_referrers(): void
    {
        $this->approveSyntheticOperation();$sessionId=str_repeat('o',40);
        $contact=Page::where('key','contact')->firstOrFail()->translation('it');$contact->update(['slug'=>'contatto-sintetico']);
        $payload=$this->formPayload($this->staleToken($sessionId,'it',time()-7201));
        $response=$this->withHeader('Referer','https://example.test/untrusted')->sessionRequest($sessionId)->post('/it/inquiries',$payload)->assertStatus(419);
        $xpath=$this->xpath($response->getContent());$this->assertSame('it',$xpath->query('//html')->item(0)->getAttribute('lang'));
        $this->assertGreaterThan(0,$xpath->query('//a[@href="'.$contact->url().'"]')->length);$this->assertStringNotContainsString('https://example.test/untrusted',$response->getContent());
        $this->assertStringContainsString('no-store',(string)$response->headers->get('Cache-Control'));$this->assertStringContainsString('noindex',(string)$response->headers->get('X-Robots-Tag'));
        $privacy=Page::where('key','privacy-request')->firstOrFail()->translation('it');$privacy->update(['slug'=>'richiesta-sintetica']);
        $data=['email'=>'guest@example.test','submission_id'=>(string)Str::uuid(),'form_token'=>$this->staleToken($sessionId,'it:privacy',time()-7201)];
        $response=$this->sessionRequest($sessionId)->post('/it/privacy-requests',$data)->assertStatus(419);$xpath=$this->xpath($response->getContent());
        $this->assertGreaterThan(0,$xpath->query('//a[@href="'.$privacy->url().'"]')->length);$this->assertDatabaseCount('inquiries',0);$this->assertDatabaseCount('data_requests',0);
        $service=app(\App\Services\PublicFormRecovery::class);
        $this->assertNull($service->url(\Illuminate\Http\Request::create('/it/inquiries','GET'),'it'));
        $this->assertNull($service->url(\Illuminate\Http\Request::create('/it/not-a-form','POST'),'it'));
        $this->assertNull($service->url(\Illuminate\Http\Request::create('/it/inquiries','POST'),'fr'));
        DB::table('pages')->where('key','privacy-request')->update(['published'=>false]);
        $this->assertNull($service->url(\Illuminate\Http\Request::create('/it/privacy-requests','POST'),'it'));
    }
    public function test_missing_native_privacy_target_prevents_storage_and_verification_mutation(): void
    {
        $this->approveSyntheticOperation();$sessionId=str_repeat('n',40);
        DB::table('pages')->where('key','privacy-request')->update(['published'=>false]);
        $payload=['email'=>'guest@example.test','submission_id'=>(string)Str::uuid(),'form_token'=>app(InquiryFormToken::class)->issue($sessionId,'es:privacy')];
        $this->sessionRequest($sessionId)->post('/es/privacy-requests',$payload)->assertStatus(503);
        $this->assertDatabaseCount('data_requests',0);$this->assertDatabaseCount('privacy_deliveries',0);$this->assertDatabaseCount('jobs',0);
        config(['londra.send_notifications'=>false]);
        $request=app(SubmitDataRequest::class)->handle(['email'=>'guest@example.test','submission_id'=>(string)Str::uuid()],'es',$sessionId);
        config(['londra.send_notifications'=>true]);
        $this->sessionRequest($sessionId)->post('/es/privacy-request/verify/'.$request->verification_token)->assertStatus(503);
        $this->assertSame('received',$request->fresh()->status);$this->assertNull($request->fresh()->verified_at);$this->assertDatabaseCount('privacy_deliveries',1);
    }
    public function test_changed_payload_does_not_reuse_a_stored_reference_or_create_duplicate_mail(): void
    {
        $this->approveSyntheticOperation();
        $sessionId=str_repeat('e',40);
        $token=app(InquiryFormToken::class)->issue($sessionId,'en');
        $payload=$this->formPayload($token);
        $this->sessionRequest($sessionId)->postJson('/en/inquiries',$payload)->assertCreated();
        $payload['phone']='+440000000001';
        $this->sessionRequest($sessionId)->postJson('/en/inquiries',$payload)->assertUnprocessable()->assertJsonValidationErrors('submission_id');
        $this->assertDatabaseCount('inquiries',1);
        $this->assertDatabaseCount('notification_deliveries',2);
    }
    public function test_transport_compatible_email_validation_rejects_unicode_and_accepts_ascii_aliases(): void
    {
        $this->approveSyntheticOperation();
        $sessionId=str_repeat('f',40);
        $token=app(InquiryFormToken::class)->issue($sessionId,'en');
        foreach(['guést@example.test','guest@éxample.test'] as $email){
            $payload=$this->formPayload($token);$payload['email']=$email;
            $this->sessionRequest($sessionId)->postJson('/en/inquiries',$payload)->assertUnprocessable()->assertJsonValidationErrors('email');
        }
        $payload=$this->formPayload($token);$payload['email']='guest+trip@example.test';
        $this->sessionRequest($sessionId)->postJson('/en/inquiries',$payload)->assertCreated();
        $this->assertSame('guest+trip@example.test',Inquiry::firstOrFail()->email);
    }
    public function test_restricted_public_information_introductions_are_localized(): void
    {
        $texts=[];
        foreach(['en','it','es'] as $locale){
            $response=$this->get('/'.$locale.'/llms.txt')->assertOk()->assertSee('info@londratransfers.co.uk')->assertDontSee('/'.$locale.'/privacy');
            $texts[$locale]=$response->getContent();
        }
        $this->assertCount(3,array_unique($texts));
        $this->assertStringNotContainsString('Private chauffeur services in London',$texts['it']);
        $this->assertStringNotContainsString('Private chauffeur services in London',$texts['es']);
    }
    public function test_full_public_export_contains_published_questions_and_highlights_only(): void
    {
        $this->approveSyntheticPolicies();
        config(['londra.preview'=>false,'londra.publication_approved'=>true,'londra.indexing_enabled'=>true]);
        $faq=Page::with('translations')->where('key','faqs')->firstOrFail();
        $service=Page::with('translations')->where('key','airport-transfers')->firstOrFail();
        $withheld=Page::where('key','refund')->firstOrFail();
        $withheld->update(['published'=>false]);
        $this->get('/llms-full.txt')->assertOk()->assertDontSee($faq->translation('en')->faq_items[0]['q']);
        $withheld->update(['published'=>true]);
        $content=$this->get('/llms-full.txt')->assertOk()->getContent();
        foreach(['en','it','es'] as $locale){
            foreach($faq->translation($locale)->faq_items as $item){$this->assertStringContainsString($item['q'],$content);$this->assertStringContainsString($item['a'],$content);}
            foreach($service->translation($locale)->highlights as $highlight){$this->assertStringContainsString($highlight,$content);}
        }
        $service->update(['published'=>false]);
        $after=$this->get('/llms-full.txt')->assertOk()->getContent();
        foreach($service->translations as $translation){$this->assertStringNotContainsString($translation->url(),$after);}
    }
    public function test_configuring_the_retired_preview_flag_cannot_hide_public_pages(): void
    {
        config(['londra.preview'=>true]);
        foreach(['/en','/it','/es'] as $url){$this->get($url)->assertOk()->assertDontSee('name="password"',false);}
        $this->get('/preview')->assertNotFound();
    }
    public function test_all_standalone_recovery_documents_have_distinct_localized_copy_theme_controls_and_navigation(): void
    {
        foreach(['en','it','es'] as $locale){
            app()->setLocale($locale);$headings=[];
            foreach([400,403,404,419,421,429,500,503] as $status){
                $html=view('errors.'.$status,['exception'=>new \Symfony\Component\HttpKernel\Exception\HttpException($status)])->render();$xpath=$this->xpath($html);
                $this->assertSame($locale,$xpath->query('//html')->item(0)->getAttribute('lang'));
                $heading=trim($xpath->query('//h1')->item(0)->textContent);$headings[]=$heading;
                $this->assertSame(trans('ui.error_'.$status.'_title',[],$locale),$heading);
                $this->assertStringContainsString(trans('ui.error_'.$status.'_body',[],$locale),$xpath->query('//body')->item(0)->textContent);
                $this->assertSame(1,$xpath->query('//button[@data-recovery-theme-toggle]')->length);
                $this->assertSame(1,$xpath->query('//script[contains(@src,"/assets/theme.js")]')->length);
                $this->assertSame(1,$xpath->query('//link[contains(@href,"/assets/recovery.css")]')->length);
                $this->assertGreaterThan(0,$xpath->query('//a[@href="/'.$locale.'"]')->length);
                $this->assertGreaterThan(0,$xpath->query('//a[@href="mailto:info@londratransfers.co.uk"]')->length);
                $this->assertDoesNotMatchRegularExpression('/stack trace|SQLSTATE|development preview|lorem ipsum/iu',$xpath->query('//body')->item(0)->textContent);
            }
            $this->assertCount(8,array_unique($headings));
            $html=view('privacy-confirm',['valid'=>true,'locale'=>$locale,'token'=>str_repeat('a',64),'privacy_url'=>Page::where('key','privacy-request')->firstOrFail()->translation($locale)->url()])->render();$xpath=$this->xpath($html);
            $this->assertSame($locale,$xpath->query('//html')->item(0)->getAttribute('lang'));
            $this->assertSame(1,$xpath->query('//script[contains(@src,"/assets/theme.js")]')->length);
            $this->assertSame(1,$xpath->query('//button[@data-recovery-theme-toggle]')->length);
            $this->assertSame(1,$xpath->query('//form[@method="post" and @action="/'.$locale.'/privacy-request/verify/'.str_repeat('a',64).'"]')->length);
        }
    }
    public function test_404_and_origin_host_421_before_locale_middleware_use_the_language_in_the_requested_path(): void
    {
        $environment=$this->app->environment();$language=app()->getLocale();$url=config('app.url');$debug=config('app.debug');
        try{
            config(['app.debug'=>false]);
            foreach(['es','it'] as $locale){
                $copy=app(\App\Services\PublicCopy::class)->forLocale($locale);
                foreach([404,421] as $status){
                    app()->setLocale('en');
                    if($status===404){$this->app->instance('env',$environment);$response=$this->get('/'.$locale.'/invalid_slug')->assertNotFound();}
                    else{$this->app->instance('env','production');config(['app.url'=>'https://canonical.example.test']);$response=$this->get('https://untrusted.example.test/'.$locale.'/invalid_slug')->assertStatus(421);}
                    $xpath=$this->xpath($response->getContent());
                    $this->assertSame($locale,$xpath->query('//html')->item(0)->getAttribute('lang'));
                    $this->assertSame($copy['error_'.$status.'_title'],trim($xpath->query('//h1')->item(0)->textContent));
                    $this->assertSame($copy['error_'.$status.'_title'].' | Londra Transfers',trim($xpath->query('//title')->item(0)->textContent));
                    $this->assertStringContainsString($copy['error_'.$status.'_body'],$xpath->query('//body')->item(0)->textContent);
                    $this->assertSame(1,$xpath->query('//header/a[@href="/'.$locale.'"]')->length);$this->assertGreaterThan(0,$xpath->query('//main//a[@href="/'.$locale.'"]')->length);
                    $this->assertSame(0,$xpath->query('//a[@href="/en"]')->length);
                }
            }
        }
        finally{$this->app->instance('env',$environment);app()->setLocale($language);config(['app.url'=>$url,'app.debug'=>$debug]);}
    }
}
