<?php
declare(strict_types=1);
namespace Tests\Feature;
use App\Actions\PublishPage;
use App\Models\{MediaAsset,Page,Vehicle};
use App\Services\{MediaDerivative,MediaReferences};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\PortalFixtures;
use Tests\TestCase;
final class MediaIntegrityTest extends TestCase
{
    use RefreshDatabase,PortalFixtures;
    protected function setUp(): void
    {
        parent::setUp();$this->seed();$this->withoutVite();config(['londra.preview'=>false]);
    }
    public function test_existing_null_and_missing_media_render_a_deliberate_text_only_detail(): void
    {
        $page=Page::with('translations')->where('key','about')->firstOrFail();
        foreach([null,'synthetic-missing-image'] as $key){
            DB::table('pages')->where('id',$page->id)->update(['image_key'=>$key]);
            foreach($page->translations as $translation){
                $html=$this->get($translation->url())->assertOk()->getContent();
                $xpath=$this->xpath($html);
                $this->assertSame(1,$xpath->query('//h1')->length);
                $this->assertStringContainsString($translation->intro,$xpath->query('//body')->item(0)->textContent);
                $this->assertSame(0,$xpath->query('//figure[contains(@class,"page-hero-photo") and not(.//img)]')->length);
            }
        }
    }
    public function test_referenced_media_cannot_be_deleted_or_renamed(): void
    {
        $media=MediaAsset::where('key','mercedes-e-class')->firstOrFail();
        foreach(['delete','rename'] as $operation){
            try{
                if($operation==='delete'){$media->delete();}else{$media->update(['key'=>'synthetic-renamed-reference']);}
                $this->fail('Referenced media was '.$operation.'d.');
            }catch(ValidationException){}
            $this->assertDatabaseHas('media_assets',['id'=>$media->id,'key'=>'mercedes-e-class']);
            $this->assertSame('mercedes-e-class',Vehicle::where('code','e-class')->value('image_key'));
            $media->refresh();
        }
    }
    public function test_fixed_homepage_image_remains_protected_without_database_references(): void
    {
        $media=MediaAsset::where('key','airport-transfer')->firstOrFail();
        DB::table('pages')->where('image_key',$media->key)->update(['image_key'=>null]);
        DB::table('vehicles')->where('image_key',$media->key)->update(['image_key'=>'mercedes-e-class']);
        $this->expectException(ValidationException::class);
        $media->delete();
    }
    public function test_unreferenced_media_can_be_deleted_without_removing_an_approved_public_asset(): void
    {
        $original=MediaAsset::where('key','westminster')->firstOrFail();
        $custom=MediaAsset::create(['key'=>'synthetic-unreferenced','public_path'=>$original->public_path,'width'=>$original->width,'height'=>$original->height,'variants'=>$original->variants,'alt_en'=>'Synthetic test image','alt_it'=>'Immagine di prova','alt_es'=>'Imagen de prueba']);
        $custom->delete();
        $this->assertDatabaseMissing('media_assets',['id'=>$custom->id]);
        $this->assertDatabaseHas('media_assets',['id'=>$original->id]);
        $this->assertFileExists(public_path($original->public_path));
    }
    public function test_missing_image_blocks_new_publication_for_a_media_required_detail(): void
    {
        $page=Page::where('key','airport-transfers')->firstOrFail();
        DB::table('pages')->where('id',$page->id)->update(['published'=>false,'image_key'=>'synthetic-unavailable']);
        $this->expectException(ValidationException::class);
        app(PublishPage::class)->handle($page->fresh());
    }
    private function photograph(string $extension,int $width,int $height): string
    {
        $path=(string)Str::uuid().'.'.$extension;
        $disk=Storage::disk('media');$disk->put($path,'');
        $image=imagecreatetruecolor($width,$height);
        imagefilledrectangle($image,0,0,$width-1,$height-1,imagecolorallocate($image,11,11,11));
        imagefilledrectangle($image,0,0,intdiv($width,2),$height-1,imagecolorallocate($image,189,161,119));
        $encoded=match($extension){'png'=>imagepng($image,$disk->path($path)),'jpg'=>imagejpeg($image,$disk->path($path),85),'webp'=>imagewebp($image,$disk->path($path),85)};
        $this->assertTrue($encoded);
        unset($image);
        return $path;
    }
    public function test_png_jpeg_and_webp_uploads_produce_verified_responsive_files_and_urls(): void
    {
        Storage::fake('media');
        $this->assertTrue(app(MediaDerivative::class)->available(),'GD WebP and EXIF support must be verified, not assumed.');
        $allPaths=[];
        foreach(['png','jpg','webp'] as $extension){
            $path=$this->photograph($extension,1800,900);
            $media=MediaAsset::create(['key'=>'synthetic-'.$extension,'upload_path'=>$path,'alt_en'=>'Synthetic photograph','alt_it'=>'Fotografia di prova','alt_es'=>'Fotografía de prueba']);
            $this->assertSame(1800,(int)$media->width);$this->assertSame(900,(int)$media->height);
            $this->assertSame([640,1000,1600],array_column($media->variants,'width'));
            $this->assertSame([320,500,800],array_column($media->variants,'height'));
            $this->assertTrue(app(MediaReferences::class)->isAvailable($media->key));
            foreach($media->variants as $variant){
                $file=Storage::disk('media')->path(substr($variant['path'],6));
                $dimensions=getimagesize($file);
                $this->assertSame([$variant['width'],$variant['height']],[$dimensions[0],$dimensions[1]]);
                $this->assertSame('image/webp',$dimensions['mime']);
                $response=$this->get('/'.$variant['path'])->assertOk();
                $this->assertStringContainsString('image/webp',(string)$response->headers->get('Content-Type'));
                $this->assertSame('nosniff',$response->headers->get('X-Content-Type-Options'));
                $allPaths[]=$variant['path'];
            }
            $page=Page::where('key','about')->firstOrFail();$page->update(['image_key'=>$media->key]);
            $html=$this->get($page->translation('en')->url())->assertOk()->getContent();
            foreach($media->variants as $variant){$this->assertStringContainsString('/'.$variant['path'].' '.$variant['width'].'w',$html);}
        }
        $this->assertCount(9,array_unique($allPaths));
    }
    public function test_small_uploads_are_not_upscaled_and_cleanup_preserves_referenced_files(): void
    {
        Storage::fake('media');
        $path=$this->photograph('png',80,40);
        $media=MediaAsset::create(['key'=>'synthetic-small','upload_path'=>$path,'alt_en'=>'Synthetic image','alt_it'=>'Immagine di prova','alt_es'=>'Imagen de prueba']);
        $this->assertSame([80],array_column($media->variants,'width'));
        $this->assertSame([40],array_column($media->variants,'height'));
        $variant=substr($media->variants[0]['path'],6);
        $unreferenced=$this->photograph('png',90,45);
        app(MediaDerivative::class)->cleanup([$path,$variant,$unreferenced,'../synthetic-traversal.png']);
        Storage::disk('media')->assertExists([$path,$variant]);
        Storage::disk('media')->assertMissing($unreferenced);
    }
    public function test_malformed_upload_is_rejected_without_creating_media_or_derivatives(): void
    {
        Storage::fake('media');
        $path=(string)Str::uuid().'.png';Storage::disk('media')->put($path,'synthetic non-image content');
        try{MediaAsset::create(['key'=>'synthetic-malformed','upload_path'=>$path,'alt_en'=>'Synthetic image','alt_it'=>'Immagine di prova','alt_es'=>'Imagen de prueba']);$this->fail('Malformed photograph was accepted.');}catch(ValidationException){}
        $this->assertDatabaseMissing('media_assets',['key'=>'synthetic-malformed']);
        $this->assertSame([],Storage::disk('media')->allFiles());
    }
    public function test_nested_save_followed_by_outer_rollback_cleans_new_derivatives_and_keeps_previous_media(): void
    {
        Storage::fake('media');
        $original=$this->photograph('png',1800,900);
        $media=MediaAsset::create(['key'=>'synthetic-rollback','upload_path'=>$original,'alt_en'=>'Synthetic image','alt_it'=>'Immagine di prova','alt_es'=>'Imagen de prueba']);
        $oldFiles=$media->managedFiles();$oldVariants=$media->variants;$replacement=$this->photograph('jpg',1200,600);$generated=[];
        try{
            DB::transaction(function()use($media,$replacement,&$generated):void{
                DB::transaction(function()use($media,$replacement,&$generated):void{$media->update(['upload_path'=>$replacement]);$generated=array_map(static fn(array $variant)=>substr($variant['path'],6),$media->variants);});
                foreach($generated as $path){Storage::disk('media')->assertExists($path);}
                throw new \RuntimeException('Synthetic outer transaction rollback.');
            });
            $this->fail('Synthetic outer transaction did not roll back.');
        }catch(\RuntimeException $error){$this->assertSame('Synthetic outer transaction rollback.',$error->getMessage());}
        $this->assertNotEmpty($generated);foreach($generated as $path){Storage::disk('media')->assertMissing($path);}
        Storage::disk('media')->assertExists($oldFiles);Storage::disk('media')->assertExists($replacement);
        $this->assertSame($original,$media->fresh()->upload_path);$this->assertSame($oldVariants,$media->fresh()->variants);
        $this->assertTrue(app(MediaReferences::class)->isAvailable($media->key));
    }
    public function test_portrait_derivatives_remain_bounded_proportional_and_have_unique_width_descriptors(): void
    {
        Storage::fake('media');$path=$this->photograph('png',1800,8000);
        $media=MediaAsset::create(['key'=>'synthetic-portrait','upload_path'=>$path,'alt_en'=>'Synthetic portrait','alt_it'=>'Immagine verticale di prova','alt_es'=>'Imagen vertical de prueba']);
        $widths=array_column($media->variants,'width');$this->assertSame($widths,array_values(array_unique($widths)));
        foreach($media->variants as $variant){
            $this->assertLessThanOrEqual(4000,$variant['height']);$this->assertLessThanOrEqual(4000000,$variant['width']*$variant['height']);
            $this->assertEqualsWithDelta(1800/8000,$variant['width']/$variant['height'],0.001);
            $actual=getimagesize(Storage::disk('media')->path(substr($variant['path'],6)));$this->assertSame([$variant['width'],$variant['height']],[$actual[0],$actual[1]]);
        }
        $this->assertTrue(app(MediaReferences::class)->isAvailable($media->key));
    }
}
