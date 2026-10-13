<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use App\Services\{MediaDerivative,MediaReferences};
use Illuminate\Support\Facades\DB;
final class MediaAsset extends Model
{
    protected $fillable = ['key', 'public_path', 'upload_path', 'width', 'height', 'variants', 'alt_en', 'alt_it', 'alt_es', 'provenance'];
    protected function casts(): array { return ['variants' => 'array']; }
    private array $generatedFiles = [];
    public static function coreKeys(): array { return array_column(json_decode((string) file_get_contents(database_path('content/media.json')), true, 512, JSON_THROW_ON_ERROR), 'key'); }
    public function canBeDeleted(): bool { $current=static::query()->find($this->id); return $current && !in_array($current->key,self::coreKeys(),true) && app(MediaReferences::class)->forKey($current->key)===[]; }
    public function delete() { return $this->getConnection()->transaction(fn() => parent::delete()); }
    public function url(): string { return $this->upload_path ? route('media', ['path' => $this->upload_path]) : asset($this->public_path); }
    public function alt(string $locale): string { return (string) $this->{'alt_'.$locale}; }
    public function save(array $options = []): bool
    {
        try {
            $saved = parent::save($options);
            $generated=$this->generatedFiles;
            if($saved&&$generated!==[]&&DB::transactionLevel()>0){DB::afterRollBack(fn()=>app(MediaDerivative::class)->cleanup($generated));}
            elseif(!$saved&&$generated!==[]){app(MediaDerivative::class)->cleanup($generated,$this->id);}
            $this->generatedFiles = []; return $saved;
        }
        catch (\Throwable $error) { $paths=$this->generatedFiles;if($this->isDirty('upload_path')&&$this->upload_path){$paths[]=$this->upload_path;}app(MediaDerivative::class)->cleanup($paths, $this->id); $this->generatedFiles = []; throw $error; }
    }
    public function managedFiles(bool $original = false): array
    {
        $upload = $original ? $this->getRawOriginal('upload_path') : $this->upload_path;
        $variants = $original ? $this->getRawOriginal('variants') : $this->variants;
        if (is_string($variants)) { $variants = json_decode($variants, true, 512, JSON_THROW_ON_ERROR); }
        $files = $upload ? [$upload] : [];
        foreach ($variants ?? [] as $variant) {
            if (is_array($variant) && is_string($variant['path'] ?? null) && preg_match('~^media/([a-f0-9-]{36}\.webp)$~D', $variant['path'], $match)) { $files[] = $match[1]; }
        }
        return array_values(array_unique($files));
    }
    protected static function booted(): void
    {
        static::saving(function (MediaAsset $media): void {
            if (!preg_match('/^[a-z0-9-]{1,80}$/D', (string) $media->key) || ($media->exists && $media->isDirty('key'))) { throw ValidationException::withMessages(['key' => 'Keep an existing media key unchanged. Use lowercase letters, numbers and hyphens for a new key.']); }
            if ($media->public_path && !preg_match('~^assets/(photos|artwork)/[a-z0-9.-]+$~D', $media->public_path)) { throw ValidationException::withMessages(['public_path' => 'Invalid asset path.']); }
            if ($media->upload_path && !preg_match('~^[a-f0-9-]{36}\.(png|jpg|jpeg|webp)$~D', $media->upload_path)) { throw ValidationException::withMessages(['upload_path' => 'Invalid upload path.']); }
            if($media->upload_path && ($media->isDirty('upload_path') || !$media->variants)) {
                $derivatives = app(MediaDerivative::class)->generate($media->upload_path);
                $media->width = $derivatives['width']; $media->height = $derivatives['height']; $media->variants = $derivatives['variants']; $media->generatedFiles = $derivatives['generated'];
            } elseif ($media->isDirty('upload_path') && !$media->upload_path && $media->public_path) {
                $defaults = json_decode((string) file_get_contents(database_path('content/media.json')), true, 512, JSON_THROW_ON_ERROR);
                $original = collect($defaults)->first(fn($asset) => $asset['key'] === $media->key && $asset['original'] === $media->public_path);
                if ($original) { $media->width = $original['width']; $media->height = $original['height']; $media->variants = $original['variants']; }
                else { $media->variants = []; }
            }
            if (!$media->upload_path && !$media->public_path) { throw ValidationException::withMessages(['upload_path' => 'Upload a photograph before saving this media asset.']); }
            if (!app(MediaReferences::class)->assetAvailable($media)) { throw ValidationException::withMessages(['upload_path' => 'The photograph or one of its responsive files is missing or invalid. Replace it before saving.']); }
        });
        static::updated(function (MediaAsset $media): void {
            if (!$media->wasChanged(['upload_path','variants'])) { return; }
            $obsolete = array_values(array_diff($media->managedFiles(true), $media->managedFiles()));
            DB::afterCommit(fn() => app(MediaDerivative::class)->cleanup($obsolete));
        });
        static::deleting(function (MediaAsset $media): void {
            $current=static::query()->lockForUpdate()->find($media->id);
            if(!$current||in_array($current->key,self::coreKeys(),true)){throw ValidationException::withMessages(['key'=>'The website photograph definitions are retained. Replace the photograph or update its details instead.']);}
            $references = app(MediaReferences::class)->forKey($current->key);
            if ($references !== []) { throw ValidationException::withMessages(['key' => 'This image is in use: '.implode('; ', $references).'. Replace those references first.']); }
            $media->setRawAttributes($current->getAttributes(), true);
        });
        static::deleted(function (MediaAsset $media): void { $paths = $media->managedFiles(); DB::afterCommit(fn() => app(MediaDerivative::class)->cleanup($paths)); });
    }
}
