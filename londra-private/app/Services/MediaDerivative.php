<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
final class MediaDerivative
{
    public function available(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreatefromstring') && function_exists('imagewebp') && function_exists('exif_read_data') && (imagetypes() & IMG_WEBP) !== 0;
    }
    public function generate(string $uploadPath): array
    {
        if (!$this->available()) { $this->invalid('The image processor requires GD WebP encoding and EXIF support.'); }
        if (!preg_match('~^[a-f0-9-]{36}\.(png|jpg|jpeg|webp)$~D', $uploadPath)) { $this->invalid('Invalid photograph path.'); }
        $disk = Storage::disk('media');
        if (!$disk->exists($uploadPath)) { $this->invalid('The uploaded photograph is missing. Upload it again.'); }
        $path = $disk->path($uploadPath);
        $dimensions = @getimagesize($path);
        $bytes = filesize($path);
        if (!$dimensions || !in_array($dimensions['mime'], ['image/png','image/jpeg','image/webp'], true) || min($dimensions[0],$dimensions[1]) < 1 || $dimensions[0] * $dimensions[1] > 40000000 || max($dimensions[0],$dimensions[1]) > 16000 || $bytes === false || $bytes > 10485760) { $this->invalid('Use a valid PNG, JPEG or WebP photograph below 10 MB, 40 megapixels and 16,000 pixels on either side.'); }
        $orientation = 1;
        if ($dimensions['mime'] === 'image/jpeg') {
            $exif = @exif_read_data($path, 'IFD0', true);
            $orientation = (int) ($exif['IFD0']['Orientation'] ?? $exif['Orientation'] ?? 1);
            if ($orientation < 1 || $orientation > 8) { $this->invalid('The photograph contains an unsupported orientation. Save an upright copy and upload it again.'); }
        }
        $orientedWidth = $orientation >= 5 ? $dimensions[1] : $dimensions[0];
        $orientedHeight = $orientation >= 5 ? $dimensions[0] : $dimensions[1];
        [$largestWidth,$largestHeight] = $this->targetDimensions($orientedWidth,$orientedHeight,1600);
        $required = $dimensions[0] * $dimensions[1] * 8 + $largestWidth * $largestHeight * 8 + $bytes * 2 + 16777216;
        $limit = $this->memoryLimit();
        if ($limit !== null && memory_get_usage(true) + $required > $limit) { $this->invalid('This photograph is too large for the available image-processing memory. Upload a smaller image.'); }
        $source = @imagecreatefromstring((string) file_get_contents($path));
        if (!$source) { $this->invalid('The photograph could not be decoded. Upload a valid image.'); }
        $generated = [];
        try {
            if ($dimensions['mime'] === 'image/jpeg') {
                $source = $this->orient($source, $orientation);
            }
            $width = imagesx($source); $height = imagesy($source);
            $variants = []; $seen = [];
            foreach (array_values(array_unique([min(640, $width), min(1000, $width), min(1600, $width)])) as $targetWidth) {
                [$targetWidth,$targetHeight] = $this->targetDimensions($width,$height,$targetWidth);
                $dimensionsKey=$targetWidth.'x'.$targetHeight;
                if(isset($seen[$dimensionsKey])){continue;}$seen[$dimensionsKey]=true;
                $target = imagecreatetruecolor($targetWidth, $targetHeight);
                if (!$target) { throw new \RuntimeException('Image allocation failed.'); }
                try {
                    imagealphablending($target, false); imagesavealpha($target, true);
                    if (!imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height)) { throw new \RuntimeException('Image resizing failed.'); }
                    $name = (string) Str::uuid().'.webp';
                    $generated[] = $name;
                    if (!@imagewebp($target, $disk->path($name), 85)) { throw new \RuntimeException('WebP encoding failed.'); }
                    $actual = @getimagesize($disk->path($name));
                    if (!$actual || $actual[0] !== $targetWidth || $actual[1] !== $targetHeight || $actual['mime'] !== 'image/webp') { throw new \RuntimeException('Image verification failed.'); }
                    $variants[] = ['path' => 'media/'.$name, 'width' => $targetWidth, 'height' => $targetHeight];
                } finally { unset($target); }
            }
            return ['width' => $width, 'height' => $height, 'variants' => $variants, 'generated' => $generated];
        } catch (\Throwable $error) {
            $this->cleanup($generated);
            if ($error instanceof ValidationException) { throw $error; }
            $this->invalid('The photograph could not be optimized. Upload a smaller valid image or check the image-processing configuration.');
        } finally { unset($source); }
    }
    public function cleanup(array $paths, ?int $ignoreMediaId = null): void
    {
        foreach (array_unique($paths) as $path) {
            if (!is_string($path) || !preg_match('~^[a-f0-9-]{36}\.(png|jpg|jpeg|webp)$~D', $path)) { continue; }
            $used = MediaAsset::query()->when($ignoreMediaId, fn($query) => $query->whereKeyNot($ignoreMediaId))->get()->contains(function (MediaAsset $media) use ($path): bool {
                if ($media->upload_path === $path) { return true; }
                return collect($media->variants ?? [])->contains(fn($variant) => is_array($variant) && ($variant['path'] ?? null) === 'media/'.$path);
            });
            if (!$used) { Storage::disk('media')->delete($path); }
        }
    }
    private function orient(\GdImage $image, int $orientation): \GdImage
    {
        if ($orientation < 1 || $orientation > 8) { $this->invalid('The photograph contains an unsupported orientation. Save an upright copy and upload it again.'); }
        if (in_array($orientation, [2,4,5,7], true)) {
            if (!imageflip($image, in_array($orientation, [4,5], true) ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL)) { throw new \RuntimeException('Orientation correction failed.'); }
        }
        $angle = match ($orientation) { 3 => 180, 5,6,7 => -90, 8 => 90, default => 0 };
        if ($angle !== 0) {
            $rotated = imagerotate($image, $angle, 0);
            if (!$rotated) { throw new \RuntimeException('Orientation correction failed.'); }
            return $rotated;
        }
        return $image;
    }
    private function targetDimensions(int $width,int $height,int $targetWidth): array
    {
        $scale=min(1,$targetWidth/$width,4000/$height,sqrt(4000000/($width*$height)));
        $outputWidth=max(1,(int)round($width*$scale));$outputHeight=max(1,(int)round($height*$scale));
        if($outputWidth*$outputHeight>4000000){$outputHeight=max(1,(int)floor(4000000/$outputWidth));}
        return [$outputWidth,$outputHeight];
    }
    private function memoryLimit(): ?int
    {
        $value = trim((string) ini_get('memory_limit'));
        if ($value === '-1') { return null; }
        if (!preg_match('/^(\d+)\s*([KMG]?)$/iD', $value, $match)) { $this->invalid('The image-processing memory limit could not be read. Check the PHP configuration.'); }
        return (int) $match[1] * match (strtoupper($match[2])) { 'G' => 1073741824, 'M' => 1048576, 'K' => 1024, default => 1 };
    }
    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['upload_path' => $message]);
    }
}
