<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\MediaAsset;
final class ReleaseCleanup
{
    public const PRIVATE_FILES = [
        'app/Http/Controllers/PreviewController.php',
        'app/Http/Middleware/PreviewGate.php',
        'resources/views/preview-login.blade.php'
];
    public const PUBLIC_FILES = [
        'assets/preview-gate.css',
        'build/assets/app-C5rauErP.css',
        'build/assets/admin-cLLgXsoV.css',
        'build/assets/app-DDh4K3f0.css',
        'build/assets/app-DozjUCyZ.js',
        'build/assets/admin-D8OjlaN7.css',
        'build/assets/app-D2b2_d-h.css',
        'build/assets/app-BPdOrPlx.js',
        'assets/source-artwork/Londra_Border.png.png',
        'assets/source-artwork/gold_phone_icon_on_black.png',
        'assets/source-artwork/golden_globe_emblem_on_black.png',
        'assets/source-artwork/golden_london_skyline_reflections_at_night.png',
        'assets/source-artwork/golden_ornamental_divider_on_black.png',
        'assets/source-artwork/golden_winged_luxury_car_emblem.png',
        'assets/source-artwork/london_skyline_in_monochrome_reflections.png',
        'assets/source-artwork/minimal_gold_envelope_icon.png',
        'assets/source-artwork/minimal_white_globe_interface_icon.png',
        'assets/source-artwork/minimalist_gold_camera_icon.png',
        'assets/source-artwork/minimalist_white_envelope_icon.png',
        'assets/source-artwork/minimalist_white_phone_icon.png',
        'assets/source-artwork/monochrome_winged_sports_car_emblem.png',
        'assets/source-artwork/original-master.svg',
        'assets/source-artwork/white_instagram_camera_icon.png',
        'assets/artwork/logo-gold-final.png',
        'assets/artwork/logo-white-final.png'
];
    public function run(): array
    {
        $references=[];
        foreach(MediaAsset::all() as $media){
            if(is_string($media->public_path)){$references[$media->public_path]=true;}
            foreach((array)($media->variants??[]) as $variant){if(is_array($variant)&&is_string($variant['path']??null)){$references[$variant['path']]=true;}}
        }
        $result=['removed'=>[],'retained_media'=>[]];
        foreach(['private'=>[base_path(),self::PRIVATE_FILES],'public'=>[public_path(),self::PUBLIC_FILES]] as $scope=>[$root,$paths]){
            $root=realpath($root);
            if($root===false){throw new \RuntimeException('The application or public directory is unavailable.');}
            foreach($paths as $relative){
                if($scope==='public'&&isset($references[$relative])){$result['retained_media'][]=$relative;continue;}
                $path=$root.'/'.$relative;
                if(!file_exists($path)&&!is_link($path)){continue;}
                $resolved=realpath($path);
                if(is_link($path)||!is_file($path)||$resolved===false||!str_starts_with($resolved,$root.'/')){throw new \RuntimeException('An obsolete release path cannot be removed safely: '.$scope.'/'.$relative);}
                if(!unlink($path)){throw new \RuntimeException('An obsolete release file could not be removed: '.$scope.'/'.$relative);}
                $result['removed'][]=$scope.'/'.$relative;
            }
        }
        return $result;
    }
}
