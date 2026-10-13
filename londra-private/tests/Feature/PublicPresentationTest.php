<?php
declare(strict_types=1);
namespace Tests\Feature;
use App\Models\{MediaAsset,Page,Setting};
use App\Services\ReleaseUpdater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
final class PublicPresentationTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->seed(); $this->withoutVite(); config(['londra.preview' => false]); }
    private function document(string $html): \DOMXPath
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        return new \DOMXPath($dom);
    }
    public function test_all_public_pages_have_complete_text_and_no_internal_copy(): void
    {
        foreach (Page::where('kind','policy')->with('translations')->get() as $policy) {
            foreach ($policy->translations as $translation) { $translation->update(['ready'=>true]); }
            $policy->update(['approved_at'=>now(),'published'=>true]);
        }
        foreach (Page::published()->with('translations')->get() as $page) {
            foreach ($page->translations as $translation) {
                $html = $this->get($translation->url())->assertOk()->getContent();
                $xpath = $this->document($html);
                $this->assertSame(1, $xpath->query('//h1')->length, $translation->url());
                $text = $xpath->query('//body')->item(0)->textContent;
                $this->assertDoesNotMatchRegularExpression('/development preview|design preview|lorem ipsum|\bdummy\b|\btemplate\b|anteprima di sviluppo|vista previa de desarrollo|private development/iu', $text);
                $this->assertSame(0, $xpath->query('//a[descendant::a]')->length);
                foreach ($xpath->query('//img') as $image) {
                    $this->assertTrue($image->hasAttribute('alt'));
                    $this->assertGreaterThan(0, (int) $image->getAttribute('width'));
                    $this->assertGreaterThan(0, (int) $image->getAttribute('height'));
                }
                foreach ($xpath->query('//*[@class="spread-copy"]') as $copy) {
                    $this->assertNotSame('', trim($xpath->query('.//h2', $copy)->item(0)->textContent));
                    $this->assertGreaterThan(150, mb_strlen(trim($copy->textContent)));
                    $this->assertGreaterThan(0, $xpath->query('.//a[@href]', $copy)->length);
                }
                foreach ($xpath->query('//*[@class="fleet-copy"]') as $copy) {
                    $this->assertNotSame('', trim($xpath->query('.//h2 | .//h3', $copy)->item(0)->textContent));
                    $this->assertGreaterThan(100, mb_strlen(trim($copy->textContent)));
                }
            }
        }
    }
    public function test_unavailable_online_enquiries_keep_visible_disabled_forms_and_direct_contact_options(): void
    {
        foreach (['en','it','es'] as $locale) {
            $response = $this->get('/'.$locale.'/'.Page::where('key','contact')->firstOrFail()->translation($locale)->slug)->assertOk();
            $response->assertSee('data-enquiry-form', false)->assertSee('tel:+447438700287', false)->assertSee('mailto:info@londratransfers.co.uk', false);
            $xpath=$this->document($response->getContent());$forms=$xpath->query('//form[@data-enquiry-form]');$this->assertSame(2,$forms->length);
            foreach($forms as $form){
                foreach(['name','email','phone'] as $name){$inputs=$xpath->query('.//input[@name="'.$name.'"]',$form);$this->assertSame(1,$inputs->length);$input=$inputs->item(0);$this->assertTrue($input->hasAttribute('disabled')||$xpath->query('ancestor::fieldset[@disabled]',$input)->length>0);}
                $buttons=$xpath->query('.//button[@type="submit"]',$form);$this->assertSame(1,$buttons->length);$button=$buttons->item(0);$this->assertTrue($button->hasAttribute('disabled')||$xpath->query('ancestor::fieldset[@disabled]',$button)->length>0);
            }
        }
        foreach (['/llms.txt','/llms-full.txt','/it/llms.txt'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('development')->assertDontSee('preview')->assertSee('info@londratransfers.co.uk');
        }
    }
    public function test_upgrade_replaces_release_copy_and_preserves_individual_edits(): void
    {
        Setting::create(['key'=>'release-2026-10-12.1','value'=>['applied_at'=>now()->toIso8601String()]]);
        $previous = json_decode(file_get_contents(database_path('content/previous-public-copy.json')), true, 512, JSON_THROW_ON_ERROR);
        $old = array_column($previous['pages'], null, 'key');
        $page = Page::with('translations')->where('key','private-transfers')->firstOrFail();
        foreach ($page->translations as $translation) {
            $translation->update(['body'=>$old['private-transfers']['translations'][$translation->locale]['body']]);
        }
        $page->translation('en')->update(['intro'=>'Our individually written introduction.']);
        Setting::create(['key'=>'interface-copy','value'=>['en'=>['preview'=>'Development preview. Enquiries are disabled.','home'=>'Welcome to Londra']]]);
        app(ReleaseUpdater::class)->run();
        $latest = json_decode(file_get_contents(database_path('content/pages.json')), true, 512, JSON_THROW_ON_ERROR);
        $latest = array_column($latest, null, 'key');
        $page->refresh();
        foreach ($page->translations as $translation) {
            $this->assertSame($latest['private-transfers']['translations'][$translation->locale]['body'], $translation->body);
        }
        $this->assertSame('Our individually written introduction.', $page->translation('en')->intro);
        $this->assertSame('Welcome to Londra', Setting::findOrFail('interface-copy')->value['en']['home']);
        $this->assertArrayNotHasKey('preview', Setting::findOrFail('interface-copy')->value['en']);
        $page->translation('en')->update(['body'=>'A subsequent individual update.']);
        app(ReleaseUpdater::class)->run();
        $this->assertSame('A subsequent individual update.', $page->fresh()->translation('en')->body);
    }
    public function test_upgrade_from_the_staging_release_preserves_custom_order_and_copy(): void
    {
        foreach (['2026-10-12.1','2026-10-12.2'] as $version) { Setting::create(['key'=>'release-'.$version,'value'=>['applied_at'=>now()->toIso8601String()]]); }
        $previous = json_decode(file_get_contents(database_path('content/previous-public-copy-2026-10-12.2.json')), true, 512, JSON_THROW_ON_ERROR);
        $old = array_column($previous['pages'], null, 'key');
        $latest = array_column(json_decode(file_get_contents(database_path('content/pages.json')), true, 512, JSON_THROW_ON_ERROR), null, 'key');
        $chauffeur = Page::where('key','chauffeur-service')->firstOrFail();
        $chauffeur->update(['sort_order'=>$old['chauffeur-service']['sort_order']]);
        $privateClients = Page::where('key','private-clients')->firstOrFail();
        $privateClients->update(['sort_order'=>73]);
        $faq = Page::with('translations')->where('key','faqs')->firstOrFail();
        foreach ($faq->translations as $translation) { $translation->update(['faq_items'=>$old['faqs']['translations'][$translation->locale]['faq_items']]); }
        $customFaq = [['q'=>'Una pregunta individual.','a'=>'Una respuesta individual.']];
        $faq->translation('es')->update(['faq_items'=>$customFaq]);
        Setting::create(['key'=>'interface-copy','value'=>['en'=>['passengers'=>$previous['ui']['en']['passengers'],'fleet_notes'=>'Our individually maintained vehicle guidance.']]]);
        app(ReleaseUpdater::class)->run();
        $this->assertSame($latest['chauffeur-service']['sort_order'], (int) $chauffeur->fresh()->sort_order);
        $this->assertSame(73, (int) $privateClients->fresh()->sort_order);
        $faq->refresh();
        foreach (['en','it'] as $locale) { $this->assertSame($latest['faqs']['translations'][$locale]['faq_items'], $faq->translation($locale)->faq_items); }
        $this->assertSame($customFaq, $faq->translation('es')->faq_items);
        $this->assertSame('guests', Setting::findOrFail('interface-copy')->value['en']['passengers']);
        $this->assertSame('Our individually maintained vehicle guidance.', Setting::findOrFail('interface-copy')->value['en']['fleet_notes']);
        $chauffeur->update(['sort_order'=>72]);
        app(ReleaseUpdater::class)->run();
        $this->assertSame(72, (int) $chauffeur->fresh()->sort_order);
    }
    public function test_language_revision_updates_installed_defaults_and_preserves_owner_edits(): void
    {
        foreach (['2026-10-12.1','2026-10-12.2','2026-10-13.1'] as $version) { Setting::create(['key'=>'release-'.$version,'value'=>['applied_at'=>now()->toIso8601String()]]); }
        $previous = json_decode(file_get_contents(database_path('content/previous-public-copy-2026-10-13.1.json')), true, 512, JSON_THROW_ON_ERROR);
        $old = array_column($previous['pages'], null, 'key');
        $latest = array_column(json_decode(file_get_contents(database_path('content/pages.json')), true, 512, JSON_THROW_ON_ERROR), null, 'key');
        $fields = ['title','eyebrow','intro','body','highlights','faq_items','seo_title','meta_description','image_alt'];
        foreach (['home','services','faqs','terms','privacy'] as $key) {
            $page = Page::with('translations')->where('key', $key)->firstOrFail();
            foreach ($page->translations as $translation) { $translation->update(array_intersect_key($old[$key]['translations'][$translation->locale], array_flip($fields)) + ['ready'=>true]); }
            if ($page->kind === 'policy') { $page->update(['approved_at'=>now(),'published'=>true]); }
        }
        Page::where('key','home')->firstOrFail()->translation('it')->update(['intro'=>'Il testo personalizzato del proprietario.']);
        $saved = $previous['ui'];
        $saved['es']['home'] = 'La portada personalizada';
        Setting::create(['key'=>'interface-copy','value'=>$saved]);
        foreach ($previous['media_alt'] as $key => $translations) { MediaAsset::where('key',$key)->firstOrFail()->update(['alt_it'=>$translations['it'],'alt_es'=>$translations['es']]); }
        MediaAsset::where('key','airport-transfer')->firstOrFail()->update(['alt_es'=>'Una descripción personalizada.']);
        app(ReleaseUpdater::class)->run();
        foreach (['home','services','faqs','terms','privacy'] as $key) {
            $page = Page::with('translations')->where('key', $key)->firstOrFail();
            foreach (['it','es'] as $locale) {
                foreach ($fields as $field) {
                    $expected = $key === 'home' && $locale === 'it' && $field === 'intro' ? 'Il testo personalizzato del proprietario.' : $latest[$key]['translations'][$locale][$field];
                    $this->assertSame($expected, $page->translation($locale)->getAttribute($field), $key.' '.$locale.' '.$field);
                }
                if ($page->kind === 'policy') {
                    $this->assertFalse($page->published);
                    $this->assertNull($page->approved_at);
                    $this->assertFalse($page->translation($locale)->ready);
                }
            }
        }
        foreach (['it','es'] as $locale) {
            $copy = app(\App\Services\PublicCopy::class)->forLocale($locale);
            $defaults = json_decode(file_get_contents(lang_path($locale.'/ui.json')), true, 512, JSON_THROW_ON_ERROR);
            foreach ($defaults as $key => $value) { $this->assertSame($locale === 'es' && $key === 'home' ? 'La portada personalizada' : $value, $copy[$key]); }
        }
        $alts = json_decode(file_get_contents(database_path('content/media-alt.json')), true, 512, JSON_THROW_ON_ERROR);
        foreach ($alts as $key => $translations) {
            $media = MediaAsset::where('key',$key)->firstOrFail();
            $this->assertSame($translations['it'], $media->alt_it);
            $this->assertSame($key === 'airport-transfer' ? 'Una descripción personalizada.' : $translations['es'], $media->alt_es);
        }
        $services = Page::where('key','services')->firstOrFail()->translation('es');
        $services->update(['intro'=>'Otra edición posterior del propietario.']);
        app(ReleaseUpdater::class)->run();
        $this->assertSame('Otra edición posterior del propietario.', $services->fresh()->intro);
    }
    public function test_latest_upgrade_updates_the_13_2_defaults_and_preserves_new_cms_fields_and_private_records(): void
    {
        foreach(['2026-10-12.1','2026-10-12.2','2026-10-13.1','2026-10-13.2'] as $version){Setting::create(['key'=>'release-'.$version,'value'=>['applied_at'=>now()->toIso8601String()]]);}
        $previous=json_decode(file_get_contents(database_path('content/previous-public-copy-2026-10-13.2.json')),true,512,JSON_THROW_ON_ERROR);
        $old=array_column($previous['pages'],null,'key');$latest=array_column(json_decode(file_get_contents(database_path('content/pages.json')),true,512,JSON_THROW_ON_ERROR),null,'key');
        $fields=['title','eyebrow','intro','body','highlights','faq_items','seo_title','meta_description','image_alt'];
        foreach(['home','private-transfers','e-class','faqs'] as $key){
            $page=Page::with('translations')->where('key',$key)->firstOrFail();
            foreach($page->translations as $translation){$translation->update(array_intersect_key($old[$key]['translations'][$translation->locale],array_flip($fields))+['detail_heading'=>null]);}
        }
        $page=Page::where('key','e-class')->firstOrFail();$page->translation('es')->update(['detail_heading'=>'Una sección revisada individualmente.']);
        Page::where('key','about')->firstOrFail()->translation('en')->update(['intro'=>'An individually approved owner introduction.']);
        $saved=$previous['ui'];$saved['it']['home']='La home mantenuta dal proprietario';Setting::create(['key'=>'interface-copy','value'=>$saved]);
        $user=\App\Models\User::create(['name'=>'Synthetic Administrator','email'=>'admin@example.test','password'=>\Illuminate\Support\Str::random(32)]);
        $record=\App\Models\Inquiry::create(['reference'=>'LT-SYNTHETIC-UPGRADE','submission_key'=>str_repeat('a',64),'payload_fingerprint'=>str_repeat('b',64),'type'=>'contact','locale'=>'en','status'=>'new','name'=>'Synthetic Guest','email'=>'guest@example.test','email_lookup'=>str_repeat('c',64),'phone'=>'+440000000000','privacy_notice_at'=>now(),'privacy_notice_version'=>'synthetic']);
        $raw=\Illuminate\Support\Facades\DB::table('inquiries')->where('id',$record->id)->value('email');$key=config('app.key');$smtp=config('mail.mailers.smtp');
        app(ReleaseUpdater::class)->run();
        foreach(['home','private-transfers','e-class','faqs'] as $pageKey){
            $page=Page::with('translations')->where('key',$pageKey)->firstOrFail();
            foreach(['en','it','es'] as $locale){
                foreach($fields as $field){$this->assertSame($latest[$pageKey]['translations'][$locale][$field],$page->translation($locale)->getAttribute($field),$pageKey.' '.$locale.' '.$field);}
                $expected=$pageKey==='e-class'&&$locale==='es'?'Una sección revisada individualmente.':($latest[$pageKey]['translations'][$locale]['detail_heading']??null);
                $this->assertSame($expected,$page->translation($locale)->detail_heading);
            }
        }
        $this->assertSame('An individually approved owner introduction.',Page::where('key','about')->firstOrFail()->translation('en')->intro);
        $this->assertSame('La home mantenuta dal proprietario',app(\App\Services\PublicCopy::class)->forLocale('it')['home']);
        $this->assertSame($user->id,\App\Models\User::firstOrFail()->id);$this->assertSame($key,config('app.key'));$this->assertSame($smtp,config('mail.mailers.smtp'));
        $this->assertSame($raw,\Illuminate\Support\Facades\DB::table('inquiries')->where('id',$record->id)->value('email'));
        $page=Page::where('key','private-transfers')->firstOrFail();$page->translation('en')->update(['detail_heading'=>'A later approved owner heading.']);app(ReleaseUpdater::class)->run();
        $this->assertSame('A later approved owner heading.',$page->fresh()->translation('en')->detail_heading);
    }
    public function test_sidebar_upgrade_from_13_3_updates_saved_release_defaults_and_preserves_current_owner_copy(): void
    {
        foreach(['2026-10-12.1','2026-10-12.2','2026-10-13.1','2026-10-13.2','2026-10-13.3'] as $version){Setting::create(['key'=>'release-'.$version,'value'=>['applied_at'=>now()->toIso8601String()]]);}
        $previous=json_decode(file_get_contents(database_path('content/previous-public-copy-2026-10-13.3.json')),true,512,JSON_THROW_ON_ERROR);$saved=$previous['ui'];
        $owners=['en'=>'Our individually maintained home label','it'=>'La home mantenuta individualmente','es'=>'La portada mantenida individualmente'];
        foreach($owners as $locale=>$label){$saved[$locale]['home']=$label;}
        Setting::create(['key'=>'interface-copy','value'=>$saved]);
        $translation=Page::where('key','about')->firstOrFail()->translation('en');$translation->update(['intro'=>'An individually maintained introduction for the sidebar upgrade test.']);
        $this->assertNull(Setting::find('release-'.ReleaseUpdater::VERSION));app(ReleaseUpdater::class)->run();
        $this->assertNotNull(Setting::find('release-'.ReleaseUpdater::VERSION));
        foreach(['en','it','es'] as $locale){
            $defaults=json_decode(file_get_contents(lang_path($locale.'/ui.json')),true,512,JSON_THROW_ON_ERROR);$copy=app(\App\Services\PublicCopy::class)->forLocale($locale);
            $this->assertNotSame($previous['ui'][$locale]['forms_unavailable'],$defaults['forms_unavailable']);$this->assertSame($defaults['forms_unavailable'],Setting::findOrFail('interface-copy')->value[$locale]['forms_unavailable']);
            foreach($defaults as $key=>$value){$this->assertSame($key==='home'?$owners[$locale]:$value,$copy[$key],$locale.' '.$key);}
        }
        $this->assertSame('An individually maintained introduction for the sidebar upgrade test.',$translation->fresh()->intro);
        $later='A later individually maintained sidebar navigation label';$value=Setting::findOrFail('interface-copy')->value;$value['en']['home']=$later;Setting::findOrFail('interface-copy')->update(['value'=>$value]);app(ReleaseUpdater::class)->run();
        $this->assertSame($later,app(\App\Services\PublicCopy::class)->forLocale('en')['home']);$this->assertSame('An individually maintained introduction for the sidebar upgrade test.',$translation->fresh()->intro);
    }
    public function test_release_readiness_requires_the_current_content_update_not_only_the_previous_release_marker(): void
    {
        Setting::create(['key'=>'release-2026-10-13.3','value'=>['applied_at'=>now()->toIso8601String()]]);
        $find=static fn(array $checks):array=>array_values(array_filter($checks,static fn(array $check)=>$check['label']==='Release content update applied'));
        $pending=$find(app(\App\Services\ReadinessReport::class)->checks());$this->assertCount(1,$pending);$this->assertFalse($pending[0]['ok']);$this->assertStringContainsString('Administration > Application update',$pending[0]['detail']);$this->assertStringContainsString(ReleaseUpdater::VERSION,$pending[0]['detail']);
        app(ReleaseUpdater::class)->run();$applied=$find(app(\App\Services\ReadinessReport::class)->checks());$this->assertCount(1,$applied);$this->assertTrue($applied[0]['ok']);
    }
}
