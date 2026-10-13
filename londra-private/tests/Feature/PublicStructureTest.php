<?php
declare(strict_types=1);
namespace Tests\Feature;
use App\Models\{Page,Setting,Vehicle};
use App\Services\PublicContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\PortalFixtures;
use Tests\TestCase;
final class PublicStructureTest extends TestCase
{
    use RefreshDatabase,PortalFixtures;
    protected function setUp(): void { parent::setUp();$this->seed();$this->withoutVite(); }
    public function test_homepage_airport_links_identify_each_destination_in_every_language(): void
    {
        $airports=Page::where('kind','airport')->with('translations')->get();
        foreach(['en','it','es'] as $locale){
            $xpath=$this->xpath($this->get('/'.$locale)->assertOk()->getContent());
            $anchors=$xpath->query('//div[contains(concat(" ",normalize-space(@class)," ")," airport-links ")]/a');
            $this->assertCount(6,$anchors);$labels=[];$destinations=[];
            foreach($anchors as $anchor){$labels[]=trim($anchor->textContent);$destinations[]=$anchor->getAttribute('href');}
            $this->assertCount(6,array_unique($labels));$this->assertCount(6,array_unique($destinations));
            foreach($airports as $airport){
                $translation=$airport->translation($locale);
                $this->assertContains($translation->title,$labels);
                $this->assertContains('/'.$locale.'/'.$translation->slug,$destinations);
            }
        }
    }
    public function test_detail_breadcrumbs_and_schema_share_the_real_collection_hierarchy(): void
    {
        foreach(['airport-transfers'=>'services','e-class'=>'fleet','heathrow'=>'airports','tours-london'=>'tours'] as $key=>$parent){
            $page=Page::with('translations')->where('key',$key)->firstOrFail();$collection=Page::with('translations')->where('key',$parent)->firstOrFail();
            foreach(['en','it','es'] as $locale){
                $xpath=$this->xpath($this->get($page->translation($locale)->url())->assertOk()->getContent());
                $nav=$xpath->query('//nav[contains(concat(" ",normalize-space(@class)," ")," breadcrumb ")]')->item(0);
                $this->assertNotNull($nav);$this->assertNotSame('',trim($nav->getAttribute('aria-label')));
                $anchors=$xpath->query('.//a',$nav);$this->assertSame(2,$anchors->length);
                $this->assertSame('/'.$locale,$anchors->item(0)->getAttribute('href'));
                $this->assertSame('/'.$locale.'/'.$collection->translation($locale)->slug,$anchors->item(1)->getAttribute('href'));
                $this->assertSame($collection->translation($locale)->title,trim($anchors->item(1)->textContent));
                $this->assertSame(1,$xpath->query('.//*[@aria-current="page"]',$nav)->length);
                $schema=json_decode($xpath->query('//script[@type="application/ld+json"]')->item(0)->textContent,true,512,JSON_THROW_ON_ERROR);
                $breadcrumb=array_values(array_filter($schema['@graph'],static fn(array $item)=>$item['@type']==='BreadcrumbList'))[0];
                $items=$breadcrumb['itemListElement'];$this->assertCount(3,$items);$this->assertSame([1,2,3],array_column($items,'position'));
                $this->assertSame($collection->translation($locale)->title,$items[1]['name']);$this->assertSame($page->translation($locale)->url(),$items[2]['item']);
            }
        }
    }
    public function test_fleet_gallery_metadata_tracks_all_reviewed_vehicle_titles_and_photographs(): void
    {
        $fleet=Page::with('translations')->where('key','fleet')->firstOrFail();
        foreach(['en','it','es'] as $locale){
            $xpath=$this->xpath($this->get($fleet->translation($locale)->url())->assertOk()->getContent());
            $panels=$xpath->query('//div[contains(concat(" ",normalize-space(@class)," ")," fleet-panel ")]');$this->assertSame(4,$panels->length);
            foreach($panels as $panel){
                $title=trim($xpath->query('.//h2',$panel)->item(0)->textContent);
                $trigger=$xpath->query('.//*[@data-open-gallery]',$panel)->item(0);$this->assertNotNull($trigger);
                $this->assertStringContainsString($title,$trigger->getAttribute('data-vehicle-name'));
                $this->assertStringContainsString($title,$trigger->getAttribute('aria-label'));
                $this->assertSame(1,$xpath->query('.//figure[contains(@class,"fleet-photo")]/img',$panel)->length);
                $this->assertGreaterThan(0,$xpath->query('.//ul[contains(@class,"fleet-facts")]/li',$panel)->length);
            }
            $this->assertSame(0,$xpath->query('//dialog[@id="gallery-dialog"]//img')->length);
        }
    }
    public function test_contact_and_enquiry_do_not_repeat_the_global_invitation_and_keep_all_direct_methods(): void
    {
        foreach(Page::whereIn('key',['contact','enquiry'])->with('translations')->get() as $page){
            foreach($page->translations as $translation){
                $html=$this->get($translation->url())->assertOk()->getContent();$xpath=$this->xpath($html);
                $this->assertSame(0,$xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," invitation ")]')->length);
                foreach(['tel:+447438700287','mailto:info@londratransfers.co.uk','https://wa.me/447438700287'] as $href){$this->assertGreaterThan(0,$xpath->query('//dialog[@id="enquiry-dialog"]//a[@href="'.$href.'"]')->length);}
                foreach($xpath->query('//*[@data-open-enquiry and self::a]') as $trigger){$this->assertNotSame('',trim($trigger->getAttribute('href')));$this->assertNotSame('#',$trigger->getAttribute('href'));}
            }
        }
    }
    public function test_essential_published_pages_cannot_be_unpublished_or_have_translation_review_removed(): void
    {
        foreach(Page::whereIn('key',['home','contact','enquiry'])->with('translations')->get() as $page){
            try{$page->update(['published'=>false]);$this->fail('Essential page was unpublished.');}catch(\Illuminate\Validation\ValidationException){}
            $this->assertTrue($page->fresh()->published);
            foreach($page->translations as $translation){
                try{$translation->update(['ready'=>false]);$this->fail('Essential translation review was removed.');}catch(\Illuminate\Validation\ValidationException){}
                $this->assertTrue($translation->fresh()->ready);
            }
        }
    }
    public function test_luggage_figures_appear_only_when_approved_and_zero_is_distinct_from_unknown(): void
    {
        $vehicle=Vehicle::where('code','e-class')->firstOrFail();$detail=$vehicle->page->translation('en')->url();$fleet=Page::where('key','fleet')->firstOrFail()->translation('en')->url();
        $vehicle->update(['luggage_approved'=>false,'large_luggage'=>0,'cabin_luggage'=>2]);
        foreach([$fleet,$detail] as $url){$xpath=$this->xpath($this->get($url)->assertOk()->getContent());$this->assertSame(0,$xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," approved-luggage ")]')->length);}
        try{$vehicle->update(['luggage_approved'=>true,'large_luggage'=>null,'cabin_luggage'=>null]);$this->fail('Unknown luggage figures were approved.');}catch(\Illuminate\Validation\ValidationException){}
        $this->assertFalse($vehicle->fresh()->luggage_approved);
        DB::table('vehicles')->where('id',$vehicle->id)->update(['luggage_approved'=>true,'large_luggage'=>null,'cabin_luggage'=>null]);
        foreach([$fleet,$detail] as $url){$xpath=$this->xpath($this->get($url)->assertOk()->getContent());$this->assertSame(0,$xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," approved-luggage ")]')->length);}
        $vehicle->refresh();
        $vehicle->update(['luggage_approved'=>true,'large_luggage'=>0,'cabin_luggage'=>2]);
        foreach([$fleet,$detail] as $url){
            $xpath=$this->xpath($this->get($url)->assertOk()->getContent());$lists=$xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," approved-luggage ")]');
            $this->assertGreaterThan(0,$lists->length);
            foreach($lists as $list){
                $items=$xpath->query('.//li',$list);$this->assertSame(2,$items->length);
                $this->assertStringContainsString('0',trim($items->item(0)->textContent));$this->assertStringContainsString('2',trim($items->item(1)->textContent));
            }
        }
        $xpath=$this->xpath($this->get($fleet)->assertOk()->getContent());
        $this->assertSame(1,$xpath->query('//dialog[@id="compare-dialog"]//*[contains(concat(" ",normalize-space(@class)," ")," approved-luggage ")]')->length);
    }
    public function test_saved_valid_public_contact_values_propagate_to_pages_recovery_links_and_schema(): void
    {
        $contact=['phone_display'=>'+44 0000 000000','phone_link'=>'+440000000000','email'=>'team@example.test','instagram'=>'synthetic_londra'];
        Setting::create(['key'=>'public-contact','value'=>$contact]);
        foreach($contact as $key=>$value){$this->assertSame($value,app(PublicContact::class)->current()[$key]);}
        foreach(['home','contact','enquiry','privacy-request'] as $key){
            $page=Page::where('key',$key)->firstOrFail();
            $html=$this->get($page->translation('en')->url())->assertOk()->getContent();
            foreach(['tel:+440000000000','mailto:team@example.test','https://wa.me/440000000000'] as $link){$this->assertStringContainsString($link,$html);}
            $xpath=$this->xpath($html);$schema=json_decode($xpath->query('//script[@type="application/ld+json"]')->item(0)->textContent,true,512,JSON_THROW_ON_ERROR);
            $organization=array_values(array_filter($schema['@graph'],static fn(array $entry)=>$entry['@type']==='Organization'))[0];
            $this->assertSame('+440000000000',$organization['telephone']);$this->assertSame('team@example.test',$organization['email']);
        }
        app()->setLocale('en');$recovery=view('errors.404')->render();
        foreach(['tel:+440000000000','mailto:team@example.test','https://wa.me/440000000000'] as $link){$this->assertStringContainsString($link,$recovery);}
    }
    public function test_invalid_stored_public_contact_values_cannot_replace_confirmed_contact_methods(): void
    {
        $defaults=app(PublicContact::class)->current();
        foreach([
            ['phone_display'=>'Invalid synthetic number','phone_link'=>'javascript:alert(1)','email'=>'javascript:alert(1)','instagram'=>'https://example.test/unsafe'],
            ['phone_display'=>['invalid'],'phone_link'=>'+440000000000','email'=>['invalid'],'instagram'=>['invalid']],
            ['phone_display'=>'+44 0000 000001','phone_link'=>'+440000000000','email'=>'invalid','instagram'=>'invalid/handle'],
        ] as $invalid){
            Setting::updateOrCreate(['key'=>'public-contact'],['value'=>$invalid]);
            $this->assertSame($defaults,app(PublicContact::class)->current());$this->assertSame($defaults,app(PublicContact::class)->recovery());
            $html=$this->get('/en')->assertOk()->getContent();$this->assertStringContainsString('tel:+447438700287',$html);$this->assertStringContainsString('mailto:info@londratransfers.co.uk',$html);
            $this->assertStringNotContainsString('javascript:alert(1)',$html);
        }
    }
}
