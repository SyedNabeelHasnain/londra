<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\{MediaAsset,Page,Vehicle};
use Illuminate\Support\Facades\Storage;
final class MediaReferences
{
    private const GLOBAL_REFERENCES = [
        'airport-transfer' => ['Homepage airport photograph'],
        'chauffeur-detail' => ['Navigation photograph', 'Homepage company photograph'],
        'tower-bridge' => ['Homepage destination photograph'],
        'private-transfer' => ['Contact invitation photograph'],
    ];
    public function requiredForKind(string $kind): bool
    {
        return in_array($kind, ['home','service','vehicle','airport','tour','editorial'], true);
    }
    public function globalKeys(): array { return array_keys(self::GLOBAL_REFERENCES); }
    public function forKey(string $key): array
    {
        $references = self::GLOBAL_REFERENCES[$key] ?? [];
        foreach (Page::where('image_key', $key)->orderBy('key')->pluck('key') as $page) { $references[] = 'Page: '.$page; }
        foreach (Vehicle::where('image_key', $key)->orderBy('code')->pluck('name') as $vehicle) { $references[] = 'Vehicle: '.$vehicle; }
        $gallerySettings = \App\Models\Setting::find('fleet-photo-galleries')?->value ?? [];
        $references = array_merge($references, \App\Services\FleetPhotoGallery::referencesFor($key, (array)$gallerySettings));
        return array_values(array_unique($references));
    }
    public function isAvailable(?string $key): bool
    {
        if (!$key || !($media = MediaAsset::where('key', $key)->first())) { return false; }
        return $this->assetAvailable($media);
    }
    public function assetAvailable(MediaAsset $media): bool
    {
        if (!$media->width || !$media->height) { return false; }
        if ($media->upload_path) {
            if (!preg_match('~^[a-f0-9-]{36}\.(png|jpg|jpeg|webp)$~D', $media->upload_path) || !Storage::disk('media')->exists($media->upload_path)) { return false; }
            $original = Storage::disk('media')->path($media->upload_path);
            if (!$media->variants) { return false; }
        } elseif (!$media->public_path || !preg_match('~^assets/(photos|artwork)/[a-z0-9.-]+$~D', $media->public_path) || !is_file(public_path($media->public_path))) { return false; }
        else { $original = public_path($media->public_path); }
        $source = @getimagesize($original);
        if (!$source || !in_array($source['mime'], ['image/png','image/jpeg','image/webp'], true)) { return false; }
        $sourceWidth = $source[0]; $sourceHeight = $source[1];
        if ($media->upload_path && $source['mime'] === 'image/jpeg') {
            if (!function_exists('exif_read_data')) { return false; }
            $exif = @exif_read_data($original, 'IFD0', true);
            $orientation = (int) ($exif['IFD0']['Orientation'] ?? $exif['Orientation'] ?? 1);
            if ($orientation < 1 || $orientation > 8) { return false; }
            if ($orientation >= 5) { [$sourceWidth,$sourceHeight]=[$sourceHeight,$sourceWidth]; }
        }
        if ($sourceWidth !== (int) $media->width || $sourceHeight !== (int) $media->height) { return false; }
        foreach ($media->variants ?? [] as $variant) {
            if (!is_array($variant) || !isset($variant['path'], $variant['width'])) { return false; }
            $path = $variant['path'];
            if (!is_string($path)) { return false; }
            if (preg_match('~^media/([a-f0-9-]{36}\.webp)$~D', $path, $match)) {
                if (!Storage::disk('media')->exists($match[1])) { return false; }
                $file = Storage::disk('media')->path($match[1]);
            } elseif (preg_match('~^assets/(photos|artwork)/[a-z0-9.-]+$~D', $path)) {
                $file = public_path($path);
            } else { return false; }
            $dimensions = is_file($file) ? @getimagesize($file) : false;
            if (!$dimensions || $dimensions[0] !== (int) $variant['width'] || (isset($variant['height']) && $dimensions[1] !== (int) $variant['height'])) { return false; }
        }
        return true;
    }
}
