<?php
declare(strict_types=1);
namespace Tests\Feature;
use App\Actions\PublishPage;
use App\Filament\Pages\InterfaceCopy;
use App\Filament\Pages\PortalSettings;
use App\Filament\Resources\ContentRevisionResource\Pages\ListContentRevisions;
use App\Filament\Resources\PageResource\Pages\{CreatePage,EditPage,ListPages};
use App\Filament\Resources\VehicleResource\Pages\{CreateVehicle,EditVehicle,ListVehicles};
use App\Filament\Resources\InquiryResource\Pages\EditInquiry;
use App\Filament\Resources\InquiryResource\RelationManagers\NotesRelationManager;
use App\Filament\Resources\RedirectResource\Pages\{CreateRedirect,EditRedirect,ListRedirects};
use App\Filament\Resources\MediaAssetResource\Pages\{CreateMediaAsset,EditMediaAsset,ListMediaAssets};
use App\Models\{ContentRevision,MediaAsset,Page,Redirect,Setting,Vehicle};
use App\Services\{InquiryExport,ReleaseUpdater};
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\PortalFixtures;
use Tests\TestCase;
final class ContentManagementTest extends TestCase
{
    use RefreshDatabase,PortalFixtures;
    protected function setUp(): void
    {
        parent::setUp();$this->seed();$this->withoutVite();config(['londra.preview'=>false]);
        $this->actingAs($this->syntheticAdministrator());
        Filament::setCurrentPanel('admin');Filament::bootCurrentPanel();
    }
    private function translations(string $slugPrefix): array
    {
        $translations=[];
        foreach(['en','it','es'] as $locale){
            $translations[]=['locale'=>$locale,'slug'=>$slugPrefix.'-'.$locale,'title'=>'Synthetic service '.$locale,'eyebrow'=>'Synthetic test','intro'=>'Synthetic isolated service introduction for application checks.','body'=>'Synthetic isolated service detail, with no business or operational claims. This exists only in the guarded test database.','detail_heading'=>'Synthetic detail heading '.$locale,'highlights'=>['Synthetic test detail'],'faq_items'=>[],'seo_title'=>'Synthetic search title '.$locale,'meta_description'=>'Synthetic isolated metadata for an application test.','image_alt'=>'Synthetic image description','ready'=>true];
        }
        return $translations;
    }
    private function createCustomPage(string $key='synthetic-service',string $kind='service'): Page
    {
        Livewire::test(CreatePage::class)->fillForm(['key'=>$key,'kind'=>$kind,'image_key'=>'westminster','sort_order'=>500,'source_reference'=>'Isolated synthetic QA only','translations'=>$this->translations($key)])->call('create')->assertHasNoFormErrors();
        return Page::with('translations')->where('key',$key)->firstOrFail();
    }
    public function test_admin_page_create_edit_slug_restore_and_delete_preserve_public_relations(): void
    {
        $page=$this->createCustomPage();
        $this->assertCount(3,$page->translations);
        $this->assertFalse($page->published);
        app(PublishPage::class)->handle($page);
        $this->assertTrue($page->published);$this->assertTrue($page->getOriginal('published'));
        foreach($page->translations as $translation){$this->get($translation->url())->assertOk()->assertSee($translation->detail_heading);}
        $originalUrl=$page->translation('en')->url();
        $edit=Livewire::test(EditPage::class,['record'=>$page->getRouteKey()]);
        $state=$edit->get('data.translations');
        $englishKey=array_key_first(array_filter($state,static fn(array $value)=>$value['locale']==='en'));
        $this->assertNotNull($englishKey);
        $edit->set('data.translations.'.$englishKey.'.body','A reviewed synthetic update for this isolated test.')->set('data.translations.'.$englishKey.'.slug','synthetic-renamed-service')->set('data.translations.'.$englishKey.'.detail_heading','A synthetic edited heading')->call('save')->assertHasNoFormErrors();
        $page->refresh();
        app(PublishPage::class)->handle($page);
        $updated=$page->translation('en');
        $this->get($originalUrl)->assertRedirect($updated->url())->assertStatus(301);
        $this->get($updated->url())->assertOk()->assertSee('A synthetic edited heading')->assertSee('A reviewed synthetic update');
        $revision=ContentRevision::where('page_id',$page->id)->where('locale','en')->latest('id')->firstOrFail();
        Livewire::test(ListContentRevisions::class)->callTableAction('restore',$revision)->assertHasNoErrors();
        $this->assertFalse($page->fresh()->published);
        $this->assertFalse($page->fresh()->translation('en')->ready);
        $this->assertSame('synthetic-renamed-service',$page->fresh()->translation('en')->slug);
        $this->assertDatabaseHas('redirects',['page_id'=>$page->id]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $page->fresh()->delete();
    }
    public function test_unpublished_custom_page_can_be_deleted_with_translations_and_history(): void
    {
        $page=$this->createCustomPage('synthetic-unused-page');
        $page->translation('en')->update(['body'=>'A synthetic replacement for the revision relation test.']);
        $this->assertDatabaseHas('content_revisions',['page_id'=>$page->id]);
        Livewire::test(ListPages::class)->assertTableActionVisible('delete',$page)->callTableAction('delete',$page)->assertHasNoErrors();
        $this->assertDatabaseMissing('pages',['id'=>$page->id]);
        $this->assertDatabaseMissing('page_translations',['page_id'=>$page->id]);
        $this->assertDatabaseMissing('content_revisions',['page_id'=>$page->id]);
    }
    public function test_shipped_pages_cannot_be_deleted_and_guest_cannot_access_content_management(): void
    {
        $page=Page::where('key','about')->firstOrFail();$page->update(['published'=>false]);
        Livewire::test(ListPages::class)->assertTableActionHidden('delete',$page);Livewire::test(EditPage::class,['record'=>$page->getRouteKey()])->assertActionHidden('delete');
        try{$page->delete();$this->fail('A required shipped page was deleted.');}catch(\Illuminate\Validation\ValidationException){}
        $this->assertDatabaseHas('pages',['id'=>$page->id]);
        auth()->logout();
        foreach(['/admin/pages','/admin/pages/create','/admin/media-assets','/admin/vehicles'] as $url){$this->get($url)->assertRedirect();}
    }
    public function test_vehicle_create_edit_deactivate_delete_changes_only_the_active_public_list(): void
    {
        $page=$this->createCustomPage('synthetic-vehicle-page','vehicle');
        Livewire::test(CreateVehicle::class)->fillForm(['code'=>'synthetic-category','name'=>'Synthetic vehicle category','page_id'=>$page->id,'image_key'=>'westminster','passengers'=>2,'large_luggage'=>null,'cabin_luggage'=>null,'luggage_approved'=>false,'active'=>true,'sort_order'=>700])->call('create')->assertHasNoFormErrors();
        $vehicle=Vehicle::where('code','synthetic-category')->firstOrFail();
        app(PublishPage::class)->handle($page);
        $fleetUrl=Page::where('key','fleet')->firstOrFail()->translation('en')->url();
        $this->get($fleetUrl)->assertOk()->assertSee($page->translation('en')->title);
        Livewire::test(EditVehicle::class,['record'=>$vehicle->getRouteKey()])->fillForm(['name'=>'Synthetic revised category','passengers'=>4,'active'=>false])->call('save')->assertHasNoFormErrors();
        $this->assertSame(4,$vehicle->fresh()->passengers);
        $this->assertFalse($vehicle->fresh()->active);
        $response=$this->get($fleetUrl)->assertOk()->assertDontSee($page->translation('en')->title);$xpath=$this->xpath($response->getContent());$detail=(string)parse_url($page->translation('en')->url(),PHP_URL_PATH);
        $this->assertTrue($page->fresh()->published);$this->assertSame(0,$xpath->query('//*[@id="site-footer"]//a[@href="'.$detail.'"]')->length);$this->assertSame(0,$xpath->query('//dialog[@id="navigation-dialog"]//a[@href="'.$detail.'"]')->length);
        $page->update(['published'=>false]);
        Livewire::test(ListVehicles::class)->assertTableActionVisible('delete',$vehicle)->callTableAction('delete',$vehicle)->assertHasNoErrors();
        $this->assertDatabaseMissing('vehicles',['id'=>$vehicle->id]);
        $this->assertSame(['e-class'=>3,'s-class'=>3,'v-class'=>7,'vito'=>8],Vehicle::orderBy('sort_order')->pluck('passengers','code')->all());
    }
    public function test_interface_language_switch_preserves_each_unsaved_draft_and_saves_only_selected_language(): void
    {
        $copy=Livewire::test(InterfaceCopy::class);
        $copy->set('data.copy.home','Synthetic English home')->set('data.locale','it')->set('data.copy.home','Home italiana sintetica')->set('data.locale','en')->assertSet('data.copy.home','Synthetic English home');
        $this->assertNull(Setting::find('interface-copy'));
        $copy->call('save')->assertHasNoFormErrors();
        $this->assertSame('Synthetic English home',Setting::findOrFail('interface-copy')->value['en']['home']);
        $this->assertArrayNotHasKey('it',Setting::findOrFail('interface-copy')->value);
        $copy->set('data.locale','it')->assertSet('data.copy.home','Home italiana sintetica')->call('save')->assertHasNoFormErrors();
        $saved=Setting::findOrFail('interface-copy')->value;
        $this->assertSame('Synthetic English home',$saved['en']['home']);
        $this->assertSame('Home italiana sintetica',$saved['it']['home']);
        $copy->set('data.copy.home','')->call('save')->assertHasFormErrors();
        $this->assertSame('Home italiana sintetica',Setting::findOrFail('interface-copy')->value['it']['home']);
    }
    public function test_enquiry_export_is_private_authorized_and_neutralizes_formula_cells_without_legacy_columns(): void
    {
        $record=$this->syntheticInquiry(['name'=>'=SUM(1)','phone'=>'+440000000000']);
        auth()->logout();
        try{app(InquiryExport::class)->one($record);$this->fail('Guest accessed a private export.');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $error){$this->assertSame(403,$error->getStatusCode());}
        $this->actingAs(\App\Models\User::firstOrFail());
        $response=app(InquiryExport::class)->one($record);ob_start();$response->sendContent();$csv=ob_get_clean();
        $rows=array_map(static fn(string $line)=>str_getcsv($line,',','"',''),preg_split('/\r?\n/',trim($csv)));
        $this->assertCount(2,$rows);$this->assertSame(count($rows[0]),count($rows[1]));
        $this->assertNotContains('Subject',$rows[0]);$this->assertNotContains('Message',$rows[0]);
        $this->assertSame("'=SUM(1)",$rows[1][array_search('Name',$rows[0],true)]);
        $this->assertSame("'+440000000000",$rows[1][array_search('Phone',$rows[0],true)]);
        $this->assertStringContainsString('no-store',(string)$response->headers->get('Cache-Control'));$this->assertSame('nosniff',$response->headers->get('X-Content-Type-Options'));
    }
    public function test_private_note_crud_keeps_received_contact_data_unchanged(): void
    {
        $record=$this->syntheticInquiry();$original=\Illuminate\Support\Facades\DB::table('inquiries')->where('id',$record->id)->first();
        $manager=Livewire::test(NotesRelationManager::class,['ownerRecord'=>$record,'pageClass'=>EditInquiry::class]);
        $manager->callTableAction('create',data:['body'=>'Synthetic private note for isolated QA.'])->assertHasNoErrors();
        $note=$record->notes()->firstOrFail();$this->assertSame(auth()->id(),$note->user_id);
        $this->assertNotSame($note->body,\Illuminate\Support\Facades\DB::table('inquiry_notes')->where('id',$note->id)->value('body'));
        $manager->callTableAction('edit',$note,data:['body'=>'A revised synthetic private note.'])->assertHasNoErrors();
        $this->assertSame('A revised synthetic private note.',$note->fresh()->body);
        $manager->callTableAction('delete',$note)->assertHasNoErrors();$this->assertDatabaseMissing('inquiry_notes',['id'=>$note->id]);
        $after=\Illuminate\Support\Facades\DB::table('inquiries')->where('id',$record->id)->first();
        foreach(['name','email','phone','reference','submission_key','payload_fingerprint'] as $field){$this->assertSame($original->{$field},$after->{$field});}
    }
    public function test_redirect_crud_changes_public_navigation_without_allowing_external_or_owned_paths(): void
    {
        $first=Page::where('key','airport-transfers')->firstOrFail();$second=Page::where('key','private-transfers')->firstOrFail();
        Livewire::test(CreateRedirect::class)->fillForm(['locale'=>'en','from_path'=>'synthetic-old-journey','page_id'=>$first->id])->call('create')->assertHasNoFormErrors();
        $redirect=\App\Models\Redirect::where('from_path','synthetic-old-journey')->firstOrFail();
        $this->get('/en/synthetic-old-journey')->assertStatus(301)->assertRedirect($first->translation('en')->url());
        Livewire::test(EditRedirect::class,['record'=>$redirect->getRouteKey()])->fillForm(['page_id'=>$second->id])->call('save')->assertHasNoFormErrors();
        $this->get('/en/synthetic-old-journey')->assertStatus(301)->assertRedirect($second->translation('en')->url());
        Livewire::test(ListRedirects::class)->callTableAction('delete',$redirect)->assertHasNoErrors();$this->get('/en/synthetic-old-journey')->assertNotFound();
        foreach(['https://example.test/remote','../admin',$first->translation('en')->slug,''] as $path){
            try{\App\Models\Redirect::create(['locale'=>'en','from_path'=>$path,'page_id'=>$second->id]);$this->fail('Unsafe or owned redirect path was accepted.');}catch(\Illuminate\Validation\ValidationException){}
        }
    }
    public function test_media_cms_upload_edit_and_delete_uses_real_optimized_files(): void
    {
        Storage::fake('media');
        Livewire::test(CreateMediaAsset::class)->fillForm(['key'=>'synthetic-media-cms','upload_path'=>UploadedFile::fake()->image('synthetic.png',1800,900),'alt_en'=>'Synthetic English image','alt_it'=>'Immagine italiana di prova','alt_es'=>'Imagen española de prueba','provenance'=>'Synthetic isolated upload'])->call('create')->assertHasNoFormErrors();
        $media=\App\Models\MediaAsset::where('key','synthetic-media-cms')->firstOrFail();
        $this->assertSame([640,1000,1600],array_column($media->variants,'width'));Storage::disk('media')->assertExists($media->upload_path);
        Livewire::test(EditMediaAsset::class,['record'=>$media->getRouteKey()])->fillForm(['alt_en'=>'A revised English image description.'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('A revised English image description.',$media->fresh()->alt_en);
        Livewire::test(ListMediaAssets::class)->assertTableActionVisible('delete',$media)->callTableAction('delete',$media)->assertHasNoErrors();$this->assertDatabaseMissing('media_assets',['id'=>$media->id]);
    }
    public function test_essential_revision_restore_requires_explicit_review_and_retains_usable_contact_page(): void
    {
        $page=Page::where('key','contact')->firstOrFail();$translation=$page->translation('en');$original=$translation->intro;
        $translation->update(['intro'=>'A synthetic contact introduction for this isolated revision test.']);
        $revision=ContentRevision::where('page_id',$page->id)->where('locale','en')->latest('id')->firstOrFail();
        $restore=Livewire::test(ListContentRevisions::class);
        $restore->callTableAction('restore',$revision,data:['reviewed'=>false])->assertHasFormErrors(['reviewed']);
        $this->assertSame('A synthetic contact introduction for this isolated revision test.',$translation->fresh()->intro);$this->assertTrue($page->fresh()->published);
        $restore->setTableActionData(['reviewed'=>true])->callMountedTableAction()->assertHasNoErrors();
        $this->assertSame($original,$translation->fresh()->intro);$this->assertTrue($translation->fresh()->ready);$this->assertTrue($page->fresh()->published);
        $this->get($translation->url())->assertOk();
    }
    public function test_public_contact_cms_rejects_a_display_number_that_dials_somewhere_else(): void
    {
        $settings=Livewire::test(PortalSettings::class)->fillForm(['phone_display'=>'+44 0000 000000','phone_link'=>'+440000000000','email'=>'team@example.test','instagram'=>'synthetic_londra'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('+440000000000',Setting::findOrFail('public-contact')->value['phone_link']);
        $settings->set('data.phone_display','+44 0000 000001')->call('save')->assertHasFormErrors(['phone_display']);
        $saved=Setting::findOrFail('public-contact')->value;$this->assertSame('+44 0000 000000',$saved['phone_display']);$this->assertSame('+440000000000',$saved['phone_link']);
    }
    public function test_pages_and_redirects_cannot_claim_the_two_real_dynamic_endpoint_paths(): void
    {
        $page=Page::where('key','about')->firstOrFail();$translation=$page->translation('en');$original=$translation->slug;
        foreach(['form-tokens','privacy-request/verify/'.str_repeat('a',64)] as $path){
            try{$translation->update(['slug'=>$path]);$this->fail('A page claimed a dynamic form endpoint.');}catch(\Illuminate\Validation\ValidationException){}
            $this->assertSame($original,$translation->fresh()->slug);$translation->refresh();
            try{\App\Models\Redirect::create(['locale'=>'en','from_path'=>$path,'page_id'=>$page->id]);$this->fail('A redirect claimed a dynamic form endpoint.');}catch(\Illuminate\Validation\ValidationException){}
            $this->assertDatabaseMissing('redirects',['locale'=>'en','from_path'=>$path]);
        }
        $this->getJson('/en/form-tokens')->assertForbidden();$this->get('/en/privacy-request/verify/'.str_repeat('a',64))->assertOk()->assertViewHas('valid',false);
        $paths=app(\App\Services\ContentPathPolicy::class);$this->assertTrue($paths->isPublicPath('form-tokens-guide'));$this->assertTrue($paths->isPublicPath('privacy-request/verify/not-a-token'));
    }
    public function test_a_preexisting_reserved_slug_can_be_repaired_without_creating_an_endpoint_redirect(): void
    {
        $page=Page::where('key','about')->firstOrFail();$translation=$page->translation('en');$original=$translation->slug;
        DB::table('page_translations')->where('id',$translation->id)->update(['slug'=>'form-tokens']);$translation->refresh();
        $this->assertFalse($translation->hasReviewContent());$translation->update(['slug'=>$original]);
        $this->assertSame($original,$translation->fresh()->slug);$this->assertDatabaseMissing('redirects',['locale'=>'en','from_path'=>'form-tokens']);
        $revision=ContentRevision::where('page_id',$page->id)->where('locale','en')->latest('id')->firstOrFail();$this->assertSame('form-tokens',$revision->snapshot['slug']);
        $this->get($translation->url())->assertOk();$this->getJson('/en/form-tokens')->assertForbidden();
    }
    public function test_unreviewing_a_custom_page_updates_the_current_editor_record_and_publish_action_immediately(): void
    {
        $page=$this->createCustomPage('synthetic-review-transition');app(PublishPage::class)->handle($page);
        $editor=Livewire::test(EditPage::class,['record'=>$page->getRouteKey()]);$state=$editor->get('data.translations');
        $key=array_key_first(array_filter($state,static fn(array $translation)=>$translation['locale']==='en'));$this->assertNotNull($key);
        $editor->set('data.translations.'.$key.'.ready',false)->call('save')->assertHasNoFormErrors()->assertActionVisible('publish');
        $this->assertFalse($editor->instance()->getRecord()->published);$this->assertFalse($page->fresh()->published);$this->assertFalse($page->fresh()->translation('en')->ready);
        $this->get($page->translation('en')->url())->assertNotFound();
        $editor->set('data.translations.'.$key.'.ready',true)->call('save')->assertHasNoFormErrors()->callAction('publish')->assertHasNoErrors()->assertActionHidden('publish');
        $this->assertTrue($editor->instance()->getRecord()->published);$this->assertTrue($editor->instance()->getRecord()->getOriginal('published'));
        $this->get($page->translation('en')->url())->assertOk();
    }
    public function test_page_path_uniqueness_is_scoped_to_language_and_reported_in_the_actual_editor(): void
    {
        $first=$this->createCustomPage('synthetic-path-owner');$second=$this->createCustomPage('synthetic-path-neighbour');
        $english=$second->translation('en');$original=$english->slug;$claimed=$first->translation('en')->slug;
        $revisions=ContentRevision::where('page_id',$second->id)->count();
        try{$english->update(['slug'=>$claimed]);$this->fail('A duplicate English page path was accepted.');}
        catch(\Illuminate\Validation\ValidationException $error){$this->assertArrayHasKey('slug',$error->errors());}
        $this->assertSame($original,$english->fresh()->slug);$this->assertSame($revisions,ContentRevision::where('page_id',$second->id)->count());
        $editor=Livewire::test(EditPage::class,['record'=>$second->getRouteKey()]);$state=$editor->get('data.translations');
        $key=array_key_first(array_filter($state,static fn(array $translation)=>$translation['locale']==='en'));$this->assertNotNull($key);
        $editor->set('data.translations.'.$key.'.slug',$claimed)->call('save')->assertHasFormErrors(['translations.'.$key.'.slug']);
        $this->assertSame($original,$english->fresh()->slug);
        $second->translation('it')->update(['slug'=>$claimed]);app(PublishPage::class)->handle($first);app(PublishPage::class)->handle($second);
        $this->assertDatabaseHas('page_translations',['page_id'=>$first->id,'locale'=>'en','slug'=>$claimed]);
        $this->assertDatabaseHas('page_translations',['page_id'=>$second->id,'locale'=>'it','slug'=>$claimed]);
        $this->get('/en/'.$claimed)->assertOk();$this->get('/it/'.$claimed)->assertOk();
    }
    public function test_redirect_path_uniqueness_is_scoped_to_language_and_does_not_block_editing_the_same_record(): void
    {
        $first=Page::where('key','airport-transfers')->firstOrFail();$second=Page::where('key','private-transfers')->firstOrFail();$path='synthetic-scoped-old-path';
        Livewire::test(CreateRedirect::class)->fillForm(['locale'=>'en','from_path'=>$path,'page_id'=>$first->id])->call('create')->assertHasNoFormErrors();
        $english=Redirect::where('locale','en')->where('from_path',$path)->firstOrFail();
        Livewire::test(CreateRedirect::class)->fillForm(['locale'=>'en','from_path'=>$path,'page_id'=>$second->id])->call('create')->assertHasFormErrors(['from_path']);
        try{Redirect::create(['locale'=>'en','from_path'=>$path,'page_id'=>$second->id]);$this->fail('A duplicate English redirect was accepted.');}
        catch(\Illuminate\Validation\ValidationException $error){$this->assertArrayHasKey('from_path',$error->errors());}
        Livewire::test(CreateRedirect::class)->fillForm(['locale'=>'it','from_path'=>$path,'page_id'=>$first->id])->call('create')->assertHasNoFormErrors();
        Livewire::test(EditRedirect::class,['record'=>$english->getRouteKey()])->fillForm(['locale'=>'en','from_path'=>$path,'page_id'=>$second->id])->call('save')->assertHasNoFormErrors();
        $this->assertSame(2,Redirect::where('from_path',$path)->count());
        $this->get('/en/'.$path)->assertStatus(301)->assertRedirect($second->translation('en')->url());
        $this->get('/it/'.$path)->assertStatus(301)->assertRedirect($first->translation('it')->url());
    }
    public function test_a_page_cannot_take_another_pages_alias_and_can_reclaim_its_own_alias_without_redirect_loops(): void
    {
        $page=$this->createCustomPage('synthetic-alias-owner');$other=$this->createCustomPage('synthetic-other-alias-owner');app(PublishPage::class)->handle($page);app(PublishPage::class)->handle($other);
        $translation=$page->translation('en');$original=$translation->slug;$owned='synthetic-own-old-alias';$foreign='synthetic-foreign-old-alias';
        Redirect::create(['locale'=>'en','from_path'=>$owned,'page_id'=>$page->id]);Redirect::create(['locale'=>'en','from_path'=>$foreign,'page_id'=>$other->id]);
        try{$translation->update(['slug'=>$foreign]);$this->fail('A page took another page’s redirect alias.');}
        catch(\Illuminate\Validation\ValidationException $error){$this->assertArrayHasKey('slug',$error->errors());}
        $editor=Livewire::test(EditPage::class,['record'=>$page->getRouteKey()]);$state=$editor->get('data.translations');
        $key=array_key_first(array_filter($state,static fn(array $value)=>$value['locale']==='en'));$this->assertNotNull($key);
        $editor->set('data.translations.'.$key.'.slug',$foreign)->call('save')->assertHasFormErrors(['translations.'.$key.'.slug']);
        $this->assertSame($original,$translation->fresh()->slug);$this->assertTrue($page->fresh()->published);
        $editor->set('data.translations.'.$key.'.slug',$owned)->call('save')->assertHasNoFormErrors();
        $this->assertSame($owned,$translation->fresh()->slug);$this->assertDatabaseMissing('redirects',['locale'=>'en','from_path'=>$owned]);
        $this->assertDatabaseHas('redirects',['locale'=>'en','from_path'=>$original,'page_id'=>$page->id]);$this->assertDatabaseHas('redirects',['locale'=>'en','from_path'=>$foreign,'page_id'=>$other->id]);
        $this->get('/en/'.$original)->assertStatus(301)->assertRedirect($translation->fresh()->url());$this->get('/en/'.$owned)->assertOk();
        $this->get('/en/'.$foreign)->assertStatus(301)->assertRedirect($other->translation('en')->url());
    }
    public function test_a_late_slug_conflict_rolls_back_translation_history_and_publication_together(): void
    {
        $page=$this->createCustomPage('synthetic-atomic-slug');$other=$this->createCustomPage('synthetic-atomic-destination');app(PublishPage::class)->handle($page);
        $translation=$page->translation('en');$original=$translation->slug;$body=$translation->body;
        DB::table('redirects')->insert(['locale'=>'en','from_path'=>$original,'page_id'=>$other->id,'created_at'=>now(),'updated_at'=>now()]);
        $revisions=ContentRevision::where('page_id',$page->id)->count();
        try{$translation->update(['slug'=>'synthetic-atomic-new-path','body'=>'Synthetic replacement that must be rolled back.','ready'=>false]);$this->fail('A conflicting old alias did not stop the slug transaction.');}
        catch(\Illuminate\Validation\ValidationException $error){$this->assertArrayHasKey('slug',$error->errors());}
        $current=$translation->fresh();$this->assertSame($original,$current->slug);$this->assertSame($body,$current->body);$this->assertTrue($current->ready);$this->assertTrue($page->fresh()->published);
        $this->assertSame($revisions,ContentRevision::where('page_id',$page->id)->count());
        $this->assertDatabaseHas('redirects',['locale'=>'en','from_path'=>$original,'page_id'=>$other->id]);$this->assertDatabaseMissing('redirects',['locale'=>'en','from_path'=>'synthetic-atomic-new-path']);
        $this->get('/en/'.$original)->assertOk();$this->get('/en/synthetic-atomic-new-path')->assertNotFound();
    }
    public function test_stale_page_instances_cannot_delete_currently_published_or_referenced_records(): void
    {
        $page=$this->createCustomPage('synthetic-stale-delete');$stale=Page::findOrFail($page->id);app(PublishPage::class)->handle($page);
        $this->assertFalse($stale->published);$this->assertFalse($stale->canBeDeleted());
        try{$stale->delete();$this->fail('A stale instance deleted a published page.');}catch(\Illuminate\Validation\ValidationException){}
        $this->assertDatabaseHas('pages',['id'=>$page->id,'published'=>true]);$page->update(['published'=>false]);$stale->refresh();$this->assertTrue($stale->canBeDeleted());
        $alias=Redirect::create(['locale'=>'en','from_path'=>'synthetic-stale-delete-alias','page_id'=>$page->id]);$this->assertFalse($stale->canBeDeleted());
        try{$stale->delete();$this->fail('A stale instance deleted a redirect destination.');}catch(\Illuminate\Validation\ValidationException){}
        $this->assertDatabaseHas('pages',['id'=>$page->id]);$this->assertDatabaseHas('page_translations',['page_id'=>$page->id]);$this->assertDatabaseHas('redirects',['id'=>$alias->id]);
        $alias->delete();$this->assertTrue($stale->canBeDeleted());$stale->delete();$this->assertDatabaseMissing('pages',['id'=>$page->id]);$this->assertDatabaseMissing('page_translations',['page_id'=>$page->id]);
    }
    public function test_default_vehicle_and_media_definitions_are_retained_while_custom_deletions_and_owner_edits_survive_upgrades(): void
    {
        $vehicle=Vehicle::where('code','e-class')->firstOrFail();$page=$vehicle->page;$page->update(['published'=>false]);$vehicle->update(['active'=>false,'name'=>'Synthetic individually maintained fleet category','passengers'=>4]);
        $this->assertFalse($vehicle->canBeDeleted());Livewire::test(ListVehicles::class)->assertTableActionHidden('delete',$vehicle);
        Livewire::test(EditVehicle::class,['record'=>$vehicle->getRouteKey()])->assertActionHidden('delete');
        try{$vehicle->delete();$this->fail('A default inactive vehicle definition was deleted.');}catch(\Illuminate\Validation\ValidationException){}
        $media=MediaAsset::where('key','westminster')->firstOrFail();DB::table('pages')->where('image_key',$media->key)->update(['image_key'=>null]);DB::table('vehicles')->where('image_key',$media->key)->update(['image_key'=>'mercedes-e-class']);
        $this->assertSame([],app(\App\Services\MediaReferences::class)->forKey($media->key));$this->assertFalse($media->canBeDeleted());Livewire::test(ListMediaAssets::class)->assertTableActionHidden('delete',$media);
        Livewire::test(EditMediaAsset::class,['record'=>$media->getRouteKey()])->assertActionHidden('delete');
        try{$media->delete();$this->fail('An unreferenced default media definition was deleted.');}catch(\Illuminate\Validation\ValidationException){}
        $media->update(['alt_en'=>'An individually maintained English photograph description.']);
        $customPage=$this->createCustomPage('synthetic-no-resurrection','vehicle');$customVehicle=Vehicle::create(['code'=>'synthetic-no-resurrection','page_id'=>$customPage->id,'name'=>'Synthetic removable category','image_key'=>'westminster','passengers'=>2,'active'=>false,'sort_order'=>900]);
        $customMedia=MediaAsset::create(['key'=>'synthetic-no-resurrection','public_path'=>$media->public_path,'width'=>$media->width,'height'=>$media->height,'variants'=>$media->variants,'alt_en'=>'Synthetic custom image','alt_it'=>'Immagine sintetica','alt_es'=>'Imagen sintética']);
        $customVehicle->delete();$customPage->delete();$customMedia->delete();
        foreach(['2026-10-12.1','2026-10-12.2','2026-10-13.1','2026-10-13.2'] as $version){Setting::create(['key'=>'release-'.$version,'value'=>['applied_at'=>now()->toIso8601String()]]);}
        foreach([1,2] as $run){
            app(ReleaseUpdater::class)->run();$this->assertFalse($vehicle->fresh()->active);$this->assertSame('Synthetic individually maintained fleet category',$vehicle->fresh()->name);$this->assertSame(4,$vehicle->fresh()->passengers);$this->assertFalse($page->fresh()->published);
            $this->assertSame('An individually maintained English photograph description.',$media->fresh()->alt_en);$this->assertCount(4,Vehicle::all());$this->assertCount(13,MediaAsset::all());
            $this->assertDatabaseMissing('vehicles',['code'=>'synthetic-no-resurrection']);$this->assertDatabaseMissing('pages',['key'=>'synthetic-no-resurrection']);$this->assertDatabaseMissing('media_assets',['key'=>'synthetic-no-resurrection']);
        }
    }
}
