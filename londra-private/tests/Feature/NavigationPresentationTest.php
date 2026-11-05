<?php
declare(strict_types=1);
namespace Tests\Feature;
use App\Filament\Resources\VehicleResource\Pages\EditVehicle;
use App\Models\{Page,Setting,Vehicle};
use App\Services\{InquiryFormToken,PublicationState,PublicCopy};
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\PortalFixtures;
use Tests\TestCase;
final class NavigationPresentationTest extends TestCase
{
    use RefreshDatabase,PortalFixtures;
    private object $transport;
    protected function setUp(): void
    {
        parent::setUp();$this->seed();$this->withoutVite();
        config(['londra.accept_inquiries'=>false,'londra.publication_approved'=>false,'londra.privacy_approved'=>false,'londra.send_notifications'=>false]);
        $this->transport=$this->fakeEmailTransport();
    }
    private function disabled(\DOMXPath $xpath,\DOMElement $control): bool
    {
        return $control->hasAttribute('disabled')||$xpath->query('ancestor::fieldset[@disabled]',$control)->length>0;
    }
    private function assertForm(\DOMXPath $xpath,\DOMElement $form,array $fields,bool $enabled,string $locale): void
    {
        $copy=app(PublicCopy::class)->forLocale($locale);
        $visible=$xpath->query('.//input[not(@type="hidden") and @name!="website"]',$form);$names=[];
        foreach($visible as $input){$names[]=$input->getAttribute('name');}
        $this->assertEqualsCanonicalizing(array_keys($fields),$names);
        foreach($fields as $name=>$type){
            $inputs=$xpath->query('.//input[@name="'.$name.'"]',$form);$this->assertSame(1,$inputs->length);$input=$inputs->item(0);$id=$input->getAttribute('id');
            $this->assertSame($type,$input->getAttribute('type'));$this->assertTrue($input->hasAttribute('required'));$this->assertNotSame('',$id);$this->assertSame(!$enabled,$this->disabled($xpath,$input));
            $labels=$xpath->query('.//label[@for="'.$id.'"]',$form);$this->assertSame(1,$labels->length);$this->assertSame($copy[$name],trim($labels->item(0)->textContent));
            $described=preg_split('/\s+/',trim($input->getAttribute('aria-describedby')));$this->assertNotSame([''],$described);
            foreach($described as $target){$this->assertSame(1,$xpath->query('//*[@id="'.$target.'"]')->length);}
        }
        $submit=$xpath->query('.//button[@type="submit"]',$form);$this->assertSame(1,$submit->length);$this->assertSame(!$enabled,$this->disabled($xpath,$submit->item(0)));
        $this->assertSame(!$enabled,$submit->item(0)->hasAttribute('disabled'));
        $availability=$form->hasAttribute('data-privacy-form')?'data-privacy-available':'data-inquiries-available';$this->assertSame($enabled?'true':'false',$form->getAttribute($availability));
        $fieldset=$xpath->query('.//fieldset[contains(concat(" ",normalize-space(@class)," ")," form-controls ")]',$form);$this->assertSame(1,$fieldset->length);$this->assertSame(!$enabled,$fieldset->item(0)->hasAttribute('disabled'));
        if(!$enabled){$notice=$form->getAttribute('aria-describedby');$this->assertNotSame('',$notice);$messages=$xpath->query('.//*[@id="'.$notice.'"]',$form);$this->assertSame(1,$messages->length);$this->assertSame($copy['forms_unavailable'],trim($messages->item(0)->textContent));$this->assertSame('status',$messages->item(0)->getAttribute('role'));}
        $this->assertNotSame('',trim($submit->item(0)->textContent));$this->assertSame('post',strtolower($form->getAttribute('method')));
        foreach(['_token','submission_id','form_token'] as $hidden){$this->assertSame(1,$xpath->query('.//input[@type="hidden" and @name="'.$hidden.'"]',$form)->length);}
    }
    private function assertDistinctIdsAndSubmissions(\DOMXPath $xpath,int $expectedSubmissions): void
    {
        $ids=[];foreach($xpath->query('//*[@id]') as $element){$ids[]=$element->getAttribute('id');}
        $this->assertNotContains('',$ids);$this->assertCount(count($ids),array_unique($ids));
        $submissions=[];foreach($xpath->query('//form//input[@name="submission_id"]') as $input){$id=$input->getAttribute('value');$this->assertTrue(Str::isUuid($id));$submissions[]=$id;}
        $this->assertCount($expectedSubmissions,$submissions);$this->assertCount($expectedSubmissions,array_unique($submissions));
    }
    private function path(Page $page,string $locale): string
    {
        return (string)parse_url($page->translation($locale)->url(),PHP_URL_PATH);
    }
    private const NAVIGATION='//dialog[@id="navigation-dialog"]//nav[contains(concat(" ",normalize-space(@class)," ")," site-navigation ")]';
    private function assertNavigation(\DOMXPath $xpath,string $locale,string $currentPath): void
    {
        foreach(['left-sidebar','right-sidebar'] as $retired){$this->assertSame(0,$xpath->query('//*[@id="'.$retired.'"]')->length);}
        $this->assertSame(0,$xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," public-shell ")]')->length);
        $navs=$xpath->query(self::NAVIGATION);$this->assertSame(1,$navs->length);$navigation=$navs->item(0);$this->assertNotSame('',trim($navigation->getAttribute('aria-label')));
        $expected=[];$activeVehiclePages=array_map('intval',Vehicle::where('active',true)->pluck('page_id')->all());
        foreach(Page::published()->with('translations')->get() as $page){if($page->translation($locale)?->ready&&($page->kind!=='vehicle'||in_array($page->id,$activeVehiclePages,true))){$expected[]=$this->path($page,$locale);}}
        $actual=[];
        foreach($xpath->query('.//a[@href]',$navigation) as $anchor){$href=$anchor->getAttribute('href');$this->assertNotSame('',trim($anchor->textContent));$this->assertContains($href,$expected);$actual[]=$href;}
        $this->assertEqualsCanonicalizing($expected,array_unique($actual));$this->assertCount(count($actual),array_unique($actual));
        $copy=app(PublicCopy::class)->forLocale($locale);$home=$xpath->query('.//a[@href="/'.$locale.'"]',$navigation);$this->assertSame(1,$home->length);$this->assertSame($copy['home'],trim($home->item(0)->textContent));
        $policy=$xpath->query('.//details[@data-navigation-group="policy"]',$navigation);
        if($policy->length){$summary=$xpath->query('./summary/span',$policy->item(0));$this->assertSame(1,$summary->length);$this->assertSame($copy['navigation_policies'],trim($summary->item(0)->textContent));}
        $current=$xpath->query('.//a[@aria-current="page"]',$navigation);$this->assertSame(1,$current->length);$this->assertSame($currentPath,$current->item(0)->getAttribute('href'));
        foreach($xpath->query('.//details',$navigation) as $group){$this->assertGreaterThan(0,$xpath->query('.//a[@href]',$group)->length);}
        $this->assertSame(0,$xpath->query('//a[descendant::a]')->length);
        $headers=$xpath->query('//header[contains(concat(" ",normalize-space(@class)," ")," site-header ")]');$this->assertSame(1,$headers->length);$header=$headers->item(0);
        $this->assertSame(0,$xpath->query('.//form',$header)->length);
        foreach(['navigation','enquiry'] as $action){$links=$xpath->query('.//a[@data-open-'.$action.']',$header);$this->assertSame(1,$links->length);$this->assertContains($links->item(0)->getAttribute('href'),$expected);$this->assertNotSame('',trim($links->item(0)->getAttribute('aria-label').$links->item(0)->textContent));}
        foreach($xpath->query('.//nav[contains(@class,"primary-nav")]//a[@href]',$header) as $anchor){$this->assertContains($anchor->getAttribute('href'),$expected);$this->assertNotSame('',trim($anchor->textContent));}
        $bars=$xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," mobile-actionbar ")]');$this->assertSame(1,$bars->length);
        $this->assertGreaterThan(0,$xpath->query('.//a[starts-with(@href,"tel:")]',$bars->item(0))->length);
        $enquiry=$xpath->query('.//a[@data-open-enquiry]',$bars->item(0));$this->assertSame(1,$enquiry->length);$this->assertContains($enquiry->item(0)->getAttribute('href'),$expected);
        foreach(['navigation-dialog'=>'drawer-left','enquiry-dialog'=>'drawer-right'] as $id=>$class){$dialogs=$xpath->query('//dialog[@id="'.$id.'"]');$this->assertSame(1,$dialogs->length);$this->assertStringContainsString($class,$dialogs->item(0)->getAttribute('class'));$this->assertNotSame('',$dialogs->item(0)->getAttribute('aria-labelledby'));}
    }
    private function customPage(string $kind,string $key): Page
    {
        $page=Page::create(['key'=>$key,'kind'=>$kind,'image_key'=>'westminster','approval_required'=>$kind==='policy','approved_at'=>$kind==='policy'?now():null,'published'=>false,'sort_order'=>700]);
        foreach(['en','it','es'] as $locale){$page->translations()->create(['locale'=>$locale,'slug'=>$key.'-'.$locale,'title'=>'Synthetic '.$kind.' '.$locale,'intro'=>'Synthetic isolated navigation content.','body'=>'Synthetic isolated content for the navigation relation check.','detail_heading'=>'Synthetic section heading','highlights'=>[],'faq_items'=>$kind==='faq'?[['q'=>'Synthetic question','a'=>'Synthetic answer']]:[],'seo_title'=>'Synthetic navigation title '.$locale,'meta_description'=>'Synthetic isolated navigation metadata.','image_alt'=>'Synthetic photograph','ready'=>true]);}
        if($kind==='vehicle'){Vehicle::create(['code'=>$key,'page_id'=>$page->id,'name'=>'Synthetic navigation vehicle','image_key'=>'westminster','passengers'=>2,'active'=>true,'sort_order'=>700]);}
        $page->update(['published'=>true]);return $page->load('translations');
    }
    public function test_closed_contact_enquiry_and_privacy_pages_keep_the_original_visible_field_scopes_and_unique_identities(): void
    {
        foreach(['en','it','es'] as $locale){
            foreach(['contact','enquiry','privacy-request'] as $key){
                $page=Page::where('key',$key)->firstOrFail();$xpath=$this->xpath($this->get($page->translation($locale)->url())->assertOk()->getContent());
                $forms=$xpath->query('//form[@data-enquiry-form]');$this->assertSame($key==='privacy-request'?1:2,$forms->length);
                foreach($forms as $form){$this->assertForm($xpath,$form,['name'=>'text','email'=>'email','phone'=>'tel'],false,$locale);$this->assertSame('/'.$locale.'/inquiries',$form->getAttribute('action'));}
                $privacy=$xpath->query('//form[@data-privacy-form]');$this->assertSame($key==='privacy-request'?1:0,$privacy->length);
                if($key==='privacy-request'){$this->assertForm($xpath,$privacy->item(0),['email'=>'email'],false,$locale);$this->assertSame('/'.$locale.'/privacy-requests',$privacy->item(0)->getAttribute('action'));}
                $this->assertDistinctIdsAndSubmissions($xpath,2);
                $copy=app(PublicCopy::class)->forLocale($locale);$this->assertStringContainsString($copy['forms_unavailable'],$xpath->query('//body')->item(0)->textContent);
                $this->assertStringNotContainsString($copy['form_intro'],$xpath->query('//body')->item(0)->textContent);
                if($key==='privacy-request'){$this->assertStringNotContainsString($copy['privacy_email_hint'],$xpath->query('//body')->item(0)->textContent);}
                foreach(['tel:+447438700287','mailto:info@londratransfers.co.uk','https://wa.me/447438700287'] as $link){$this->assertGreaterThan(0,$xpath->query('//a[@href="'.$link.'"]')->length);}
                $this->assertSame(0,$xpath->query('//form//input[@name="subject" or @name="message" or @name="bank_account" or @name="card_number" or @name="payment"] | //form//textarea')->length);
            }
        }
    }
    public function test_synthetic_operating_approval_enables_existing_forms_and_stale_smtp_disables_them_without_removing_fields(): void
    {
        $this->transport=$this->approveSyntheticOperation();
        foreach(['en','it','es'] as $locale){
            foreach(['contact','enquiry','privacy-request'] as $key){
                $page=Page::where('key',$key)->firstOrFail();$xpath=$this->xpath($this->get($page->translation($locale)->url())->assertOk()->getContent());
                $forms=$xpath->query('//form[@data-enquiry-form]');$this->assertSame($key==='privacy-request'?1:2,$forms->length);
                foreach($forms as $form){$this->assertForm($xpath,$form,['name'=>'text','email'=>'email','phone'=>'tel'],true,$locale);}
                if($key==='privacy-request'){$privacy=$xpath->query('//form[@data-privacy-form]');$this->assertSame(1,$privacy->length);$this->assertForm($xpath,$privacy->item(0),['email'=>'email'],true,$locale);}
                $this->assertDistinctIdsAndSubmissions($xpath,2);
            }
        }
        config(['mail.mailers.smtp.host'=>'changed-synthetic.example.test']);$this->assertFalse(app(PublicationState::class)->inquiriesAllowed());$this->assertFalse(app(PublicationState::class)->privacyRequestsAllowed());
        foreach(['en','it','es'] as $locale){
            $page=Page::where('key','privacy-request')->firstOrFail();$xpath=$this->xpath($this->get($page->translation($locale)->url())->assertOk()->getContent());
            foreach($xpath->query('//form[@data-enquiry-form]') as $form){$this->assertForm($xpath,$form,['name'=>'text','email'=>'email','phone'=>'tel'],false,$locale);}
            $privacy=$xpath->query('//form[@data-privacy-form]');$this->assertSame(1,$privacy->length);$this->assertForm($xpath,$privacy->item(0),['email'=>'email'],false,$locale);
        }
        $this->assertCount(0,$this->transport->sent);
    }
    public function test_manually_submitting_a_disabled_form_cannot_store_or_dispatch_any_customer_data(): void
    {
        foreach(['en','it','es'] as $index=>$locale){
            $session=str_repeat(chr(112+$index),40);$tokens=app(InquiryFormToken::class);
            $enquiry=['name'=>'Synthetic Guest','email'=>'guest@example.test','phone'=>'+440000000000','type'=>'contact','page_key'=>'contact','submission_id'=>(string)Str::uuid(),'form_token'=>$tokens->issue($session,$locale)];
            $privacy=['email'=>'guest@example.test','submission_id'=>(string)Str::uuid(),'form_token'=>$tokens->issue($session,$locale.':privacy')];
            $this->withCredentials()->withCookie(config('session.cookie'),$session)->postJson('/'.$locale.'/inquiries',$enquiry)->assertForbidden();
            $this->withCredentials()->withCookie(config('session.cookie'),$session)->postJson('/'.$locale.'/privacy-requests',$privacy)->assertForbidden();
        }
        foreach(['inquiries','data_requests','notification_deliveries','privacy_deliveries','jobs'] as $table){$this->assertDatabaseCount($table,0);}$this->assertCount(0,$this->transport->sent);
    }
    public function test_every_published_language_page_has_complete_current_navigation_and_real_action_fallbacks(): void
    {
        foreach(Page::published()->with('translations')->get() as $page){
            foreach(['en','it','es'] as $locale){
                $response=$this->get($page->translation($locale)->url())->assertOk();$xpath=$this->xpath($response->getContent());$this->assertNavigation($xpath,$locale,$this->path($page,$locale));
                $this->assertSame($locale,$xpath->query('//html')->item(0)->getAttribute('lang'));$this->assertSame(1,$xpath->query('//h1')->length);
                $this->assertDistinctIdsAndSubmissions($xpath,in_array($page->key,['contact','enquiry','privacy-request'],true)?2:1);
                $this->assertDoesNotMatchRegularExpression('/development preview|design preview|lorem ipsum|\bdummy\b|\btemplate\b|\bplaceholder\b|frontend|backend|anteprima di sviluppo|vista previa de desarrollo/iu',$xpath->query('//body')->item(0)->textContent);
            }
        }
    }
    public function test_navigation_catalogue_includes_custom_published_records_and_tracks_locale_edits_and_withdrawal(): void
    {
        $custom=[];foreach(['service','vehicle','airport','tour','editorial','faq','directory','policy'] as $kind){$custom[$kind]=$this->customPage($kind,'synthetic-navigation-'.$kind);}
        foreach(['en','it','es'] as $locale){
            $xpath=$this->xpath($this->get('/'.$locale)->assertOk()->getContent());$this->assertNavigation($xpath,$locale,'/'.$locale);
            foreach($custom as $page){$links=$xpath->query(self::NAVIGATION.'//a[@href="'.$this->path($page,$locale).'"]');$this->assertSame(1,$links->length);$this->assertSame($page->translation($locale)->title,trim($links->item(0)->textContent));}
        }
        $tour=$custom['tour'];$spanish=$tour->translation('es');$oldPath=$this->path($tour,'es');$spanish->update(['slug'=>'recorrido-sintetico-actualizado','title'=>'Recorrido sintético actualizado']);
        $about=Page::where('key','about')->firstOrFail();$about->translation('it')->update(['slug'=>'azienda-sintetica-aggiornata','title'=>'Titolo aziendale aggiornato']);
        foreach(['en','it','es'] as $locale){
            $xpath=$this->xpath($this->get('/'.$locale)->assertOk()->getContent());$this->assertNavigation($xpath,$locale,'/'.$locale);$current=$tour->fresh()->translation($locale);
            $links=$xpath->query(self::NAVIGATION.'//a[@href="'.(string)parse_url($current->url(),PHP_URL_PATH).'"]');$this->assertSame(1,$links->length);$this->assertSame($current->title,trim($links->item(0)->textContent));
            if($locale==='es'){$this->assertSame(0,$xpath->query(self::NAVIGATION.'//a[@href="'.$oldPath.'"]')->length);}
        }
        $this->get($oldPath)->assertStatus(301)->assertRedirect($spanish->url());
        $custom['service']->update(['published'=>false]);$about->update(['published'=>false]);
        $editorial=$custom['editorial'];DB::table('page_translations')->where('page_id',$editorial->id)->where('locale','it')->update(['ready'=>false]);
        foreach(['en','it','es'] as $locale){
            $xpath=$this->xpath($this->get('/'.$locale)->assertOk()->getContent());$this->assertNavigation($xpath,$locale,'/'.$locale);
            foreach([$custom['service'],$about] as $page){$this->assertSame(0,$xpath->query(self::NAVIGATION.'//a[@href="'.$this->path($page->fresh(),$locale).'"]')->length);}
            $this->assertSame($locale==='it'?0:1,$xpath->query(self::NAVIGATION.'//a[@href="'.$this->path($editorial,$locale).'"]')->length);
        }
    }
    public function test_navigation_omits_empty_collection_groups_without_hiding_essential_navigation(): void
    {
        foreach(Page::whereIn('kind',['service','services','vehicle','fleet','airport','airports','tour','tours'])->get() as $page){$page->update(['published'=>false]);}
        foreach(['en','it','es'] as $locale){
            $page=Page::where('key','contact')->firstOrFail();$xpath=$this->xpath($this->get($page->translation($locale)->url())->assertOk()->getContent());$this->assertNavigation($xpath,$locale,$this->path($page,$locale));
            $this->assertSame(0,$xpath->query(self::NAVIGATION.'//details[@data-navigation-group="service" or @data-navigation-group="vehicle" or @data-navigation-group="airport" or @data-navigation-group="tour"]')->length);
        }
    }
    public function test_independent_intake_gates_enable_only_their_own_visible_forms(): void
    {
        $this->transport=$this->approveSyntheticOperation();config(['londra.accept_inquiries'=>false]);$state=app(PublicationState::class);$this->assertFalse($state->inquiriesAllowed());$this->assertTrue($state->privacyRequestsAllowed());
        foreach(['en','it','es'] as $locale){
            $page=Page::where('key','privacy-request')->firstOrFail();$xpath=$this->xpath($this->get($page->translation($locale)->url())->assertOk()->getContent());$this->assertNavigation($xpath,$locale,$this->path($page,$locale));
            foreach($xpath->query('//form[@data-enquiry-form]') as $form){$this->assertForm($xpath,$form,['name'=>'text','email'=>'email','phone'=>'tel'],false,$locale);$this->assertSame('false',$form->getAttribute('data-inquiries-available'));}
            $forms=$xpath->query('//form[@data-privacy-form]');$this->assertSame(1,$forms->length);$this->assertForm($xpath,$forms->item(0),['email'=>'email'],true,$locale);$this->assertSame('true',$forms->item(0)->getAttribute('data-privacy-available'));
        }
        config(['londra.accept_inquiries'=>true]);Page::where('key','privacy-request')->firstOrFail()->update(['published'=>false]);$this->assertTrue($state->inquiriesAllowed());$this->assertFalse($state->privacyRequestsAllowed());
        foreach(['en','it','es'] as $locale){
            $page=Page::where('key','contact')->firstOrFail();$xpath=$this->xpath($this->get($page->translation($locale)->url())->assertOk()->getContent());$this->assertNavigation($xpath,$locale,$this->path($page,$locale));
            foreach($xpath->query('//form[@data-enquiry-form]') as $form){$this->assertForm($xpath,$form,['name'=>'text','email'=>'email','phone'=>'tel'],true,$locale);$this->assertSame('true',$form->getAttribute('data-inquiries-available'));}
        }
        $this->assertCount(0,$this->transport->sent);
    }
    public function test_contact_actions_use_current_validated_public_contact_values(): void
    {
        Setting::create(['key'=>'public-contact','value'=>['phone_display'=>'+44 0000 000000','phone_link'=>'+440000000000','email'=>'team@example.test','instagram'=>'synthetic_londra']]);
        foreach(['en','it','es'] as $locale){
            $xpath=$this->xpath($this->get('/'.$locale)->assertOk()->getContent());
            foreach(['//dialog[@id="enquiry-dialog"]','//*[@id="site-footer"]'] as $surface){
                $nodes=$xpath->query($surface);$this->assertSame(1,$nodes->length);
                foreach(['tel:+440000000000','mailto:team@example.test','https://wa.me/440000000000'] as $link){$this->assertGreaterThan(0,$xpath->query('.//a[@href="'.$link.'"]',$nodes->item(0))->length);}
            }
            $this->assertGreaterThan(0,$xpath->query('//a[@href="https://www.instagram.com/synthetic_londra/"]')->length);
            $this->assertSame(0,$xpath->query('//a[@href="tel:+447438700287" or @href="mailto:info@londratransfers.co.uk"]')->length);
        }
    }
    public function test_navigation_home_honors_reviewed_interface_copy_and_approved_policy_links_have_their_own_localized_heading(): void
    {
        $this->approveSyntheticPolicies();$labels=['en'=>'Our maintained home label','it'=>'La nostra home','es'=>'Nuestra página principal'];$saved=[];
        foreach($labels as $locale=>$label){$saved[$locale]=['home'=>$label];}Setting::create(['key'=>'interface-copy','value'=>$saved]);
        foreach(['en','it','es'] as $locale){
            $xpath=$this->xpath($this->get('/'.$locale)->assertOk()->getContent());$this->assertNavigation($xpath,$locale,'/'.$locale);
            $home=$xpath->query(self::NAVIGATION.'//a[@href="/'.$locale.'"]');$this->assertSame(1,$home->length);$this->assertSame($labels[$locale],trim($home->item(0)->textContent));
            $drawer=$xpath->query('//dialog[@id="navigation-dialog"]//a[@class="nav-home"]');$this->assertSame(1,$drawer->length);$this->assertSame($labels[$locale],trim($drawer->item(0)->textContent));
            $groups=$xpath->query(self::NAVIGATION.'//details[@data-navigation-group="policy"]');$this->assertSame(1,$groups->length);
            foreach(Page::where('kind','policy')->with('translations')->get() as $page){$links=$xpath->query('.//a[@href="'.$this->path($page,$locale).'"]',$groups->item(0));$this->assertSame(1,$links->length);$this->assertSame($page->translation($locale)->title,trim($links->item(0)->textContent));}
        }
    }
    public function test_real_vehicle_edit_deactivation_and_reactivation_update_both_navigation_surfaces_without_withdrawing_the_page(): void
    {
        $page=$this->customPage('vehicle','synthetic-navigation-active');$vehicle=Vehicle::where('page_id',$page->id)->firstOrFail();$this->actingAs($this->syntheticAdministrator());Filament::setCurrentPanel('admin');Filament::bootCurrentPanel();
        $editor=Livewire::test(EditVehicle::class,['record'=>$vehicle->getRouteKey()]);
        foreach([false,true] as $active){
            $editor->fillForm(['active'=>$active])->call('save')->assertHasNoFormErrors();$this->assertSame($active,$vehicle->fresh()->active);$this->assertTrue($page->fresh()->published);
            foreach(['en','it','es'] as $locale){
                $xpath=$this->xpath($this->get('/'.$locale)->assertOk()->getContent());$this->assertNavigation($xpath,$locale,'/'.$locale);$path=$this->path($page,$locale);
                foreach([self::NAVIGATION] as $surface){$this->assertSame($active?1:0,$xpath->query($surface.'//a[@href="'.$path.'"]')->length);}
            }
        }
    }
}
