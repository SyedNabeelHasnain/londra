<?php
declare(strict_types=1);
namespace Tests\Feature;
use App\Models\MediaAsset;
use App\Services\ReleaseCleanup;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
final class ReleaseCleanupTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp();$this->seed(); }
    private function withTemporaryRoots(callable $test): void
    {
        $paths=['base'=>base_path(),'public'=>public_path(),'bootstrap'=>$this->app->bootstrapPath(),'lang'=>lang_path()];
        $temporary=sys_get_temp_dir().'/londra-cleanup-'.Str::uuid();
        $private=$temporary.'/private';$public=$temporary.'/public';
        $files=new Filesystem();$files->makeDirectory($private,0755,true);$files->makeDirectory($public,0755,true);
        try{$this->app->setBasePath($private)->usePublicPath($public);$test($private,$public,$temporary,$files);}
        finally{$this->app->setBasePath($paths['base'])->usePublicPath($paths['public'])->useBootstrapPath($paths['bootstrap'])->useLangPath($paths['lang']);$files->deleteDirectory($temporary);}
    }
    private function write(Filesystem $files,string $path,string $content): void
    {
        $files->ensureDirectoryExists(dirname($path));$files->put($path,$content);
    }
    public function test_cleanup_removes_only_known_obsolete_files_retains_both_media_reference_types_and_is_idempotent(): void
    {
        $obsolete=[
            'private'=>['app/Http/Controllers/PreviewController.php','app/Http/Middleware/PreviewGate.php','resources/views/preview-login.blade.php'],
            'public'=>['assets/preview-gate.css','build/assets/admin-cLLgXsoV.css','build/assets/app-DDh4K3f0.css','build/assets/app-DozjUCyZ.js','build/assets/admin-D8OjlaN7.css','build/assets/app-D2b2_d-h.css','build/assets/app-BPdOrPlx.js','assets/source-artwork/Londra_Border.png.png','assets/source-artwork/gold_phone_icon_on_black.png','assets/source-artwork/golden_globe_emblem_on_black.png','assets/source-artwork/golden_london_skyline_reflections_at_night.png','assets/source-artwork/golden_ornamental_divider_on_black.png','assets/source-artwork/golden_winged_luxury_car_emblem.png','assets/source-artwork/london_skyline_in_monochrome_reflections.png','assets/source-artwork/minimal_gold_envelope_icon.png','assets/source-artwork/minimal_white_globe_interface_icon.png','assets/source-artwork/minimalist_gold_camera_icon.png','assets/source-artwork/minimalist_white_envelope_icon.png','assets/source-artwork/minimalist_white_phone_icon.png','assets/source-artwork/monochrome_winged_sports_car_emblem.png','assets/source-artwork/original-master.svg','assets/source-artwork/white_instagram_camera_icon.png','assets/artwork/logo-gold-final.png','assets/artwork/logo-white-final.png'],
        ];
        $obsolete['public'][]='build/assets/app-C5rauErP.css';
        $references=['assets/artwork/logo-gold-final.png','assets/source-artwork/original-master.svg'];
        $media=MediaAsset::where('key','westminster')->firstOrFail();
        DB::table('media_assets')->where('id',$media->id)->update(['public_path'=>$references[0],'variants'=>json_encode([['path'=>$references[1],'width'=>640,'height'=>320]],JSON_THROW_ON_ERROR)]);
        $this->withTemporaryRoots(function(string $private,string $public,string $temporary,Filesystem $files)use($obsolete,$references):void{
            foreach($obsolete as $scope=>$paths){foreach($paths as $relative){$this->write($files,($scope==='private'?$private:$public).'/'.$relative,'Synthetic obsolete file '.$relative);}}
            $sentinels=[
                $private.'/.env'=>'Synthetic environment sentinel',
                $private.'/.londra-installed'=>'Synthetic installed sentinel',
                $private.'/storage/app/private/brand-assets/original-master.svg'=>'Synthetic private master artwork',
                $private.'/storage/app/private/inquiries/synthetic.txt'=>'Synthetic private inquiry sentinel',
                $private.'/app/Http/Controllers/CurrentController.php'=>'Synthetic current controller',
                $public.'/.londra-installed'=>'Synthetic public installed sentinel',
                $public.'/.londra-install.lock'=>'Synthetic installation lock',
                $public.'/media/00000000-0000-4000-8000-000000000000.webp'=>'Synthetic customer upload',
                $public.'/assets/photos/current-live.webp'=>'Synthetic current photograph',
                $public.'/build/assets/app-current-live.js'=>'Synthetic current application bundle',
                $public.'/build/assets/admin-gWgP6zCH.css'=>'Synthetic current administrator stylesheet',
                $public.'/build/assets/app-CWXEs6oC.css'=>'Synthetic current public stylesheet',
                $public.'/build/assets/inter-latin-DSpq6sGJ.woff2'=>'Synthetic current public font',
                $public.'/build/assets/app-BiBnWCCk.js'=>'Synthetic current public script',
                $public.'/assets/source-artwork/owner-approved.svg'=>'Synthetic unrelated owner artwork',
            ];
            foreach($sentinels as $path=>$content){$this->write($files,$path,$content);}
            $result=app(ReleaseCleanup::class)->run();$expected=[];
            foreach($obsolete as $scope=>$paths){foreach($paths as $relative){
                $path=($scope==='private'?$private:$public).'/'.$relative;
                if($scope==='public'&&in_array($relative,$references,true)){$this->assertFileExists($path);$this->assertSame('Synthetic obsolete file '.$relative,$files->get($path));}
                else{$expected[]=$scope.'/'.$relative;$this->assertFileDoesNotExist($path);}
            }}
            $this->assertEqualsCanonicalizing($expected,$result['removed']);$this->assertCount(26,$result['removed']);$this->assertEqualsCanonicalizing($references,$result['retained_media']);
            foreach($sentinels as $path=>$content){$this->assertSame($content,$files->get($path));}
            $again=app(ReleaseCleanup::class)->run();$this->assertSame([],$again['removed']);$this->assertEqualsCanonicalizing($references,$again['retained_media']);
            foreach($sentinels as $path=>$content){$this->assertSame($content,$files->get($path));}
        });
    }
    public function test_cleanup_refuses_directories_symlinks_and_paths_resolving_outside_the_public_root(): void
    {
        foreach(['directory','symlink','ancestor-symlink'] as $kind){
            $this->withTemporaryRoots(function(string $private,string $public,string $temporary,Filesystem $files)use($kind):void{
                $outside=$temporary.'/outside/preview-gate.css';$this->write($files,$outside,'Synthetic outside-root sentinel');
                $target=$public.'/assets/preview-gate.css';
                if($kind==='directory'){$files->makeDirectory($target,0755,true);$this->write($files,$target.'/retained.txt','Synthetic directory sentinel');}
                elseif($kind==='symlink'){$files->ensureDirectoryExists(dirname($target));$this->assertTrue(symlink($outside,$target));}
                else{$this->assertTrue(symlink(dirname($outside),$public.'/assets'));}
                try{app(ReleaseCleanup::class)->run();$this->fail('Unsafe obsolete '.$kind.' was removed.');}
                catch(\RuntimeException $error){$this->assertStringContainsString('cannot be removed safely',$error->getMessage());}
                $this->assertSame('Synthetic outside-root sentinel',$files->get($outside));
                if($kind==='directory'){$this->assertDirectoryExists($target);$this->assertSame('Synthetic directory sentinel',$files->get($target.'/retained.txt'));}
                elseif($kind==='symlink'){$this->assertTrue(is_link($target));}
                else{$this->assertTrue(is_link($public.'/assets'));}
            });
        }
    }
}
