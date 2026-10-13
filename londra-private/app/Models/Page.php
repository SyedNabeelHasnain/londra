<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use App\Domain\ReleasePolicy;
use App\Services\MediaReferences;
final class Page extends Model
{
    protected $fillable = ['key', 'kind', 'image_key', 'published', 'approval_required', 'approved_at', 'sort_order', 'source_reference'];
    protected function casts(): array { return ['published' => 'boolean', 'approval_required' => 'boolean', 'approved_at' => 'datetime']; }
    public function translations(): HasMany { return $this->hasMany(PageTranslation::class); }
    public function revisions(): HasMany { return $this->hasMany(ContentRevision::class); }
    public function scopePublished(Builder $query): Builder { return $query->where('published', true); }
    public function translation(string $locale): ?PageTranslation { return $this->translations->firstWhere('locale', $locale); }
    public function reviewedLocales(): array
    {
        return $this->translations()->get()->filter(function(PageTranslation $translation): bool { $translation->setRelation('page',$this); return $translation->ready&&$translation->hasReviewContent(); })->pluck('locale')->all();
    }
    public static function requiredKeys(): array { return ['home','contact','enquiry']; }
    public static function kinds(): array { return ['home','services','service','fleet','vehicle','airports','airport','tours','tour','editorial','contact','enquiry','faq','directory','policy','privacy-request']; }
    public static function coreKeys(): array
    {
        return array_column(json_decode((string) file_get_contents(database_path('content/pages.json')), true, 512, JSON_THROW_ON_ERROR), 'key');
    }
    public function canBeDeleted(): bool
    {
        return static::query()->whereKey($this->id)->where('published', false)->whereNotIn('key', self::coreKeys())->exists() && !Vehicle::where('page_id', $this->id)->exists() && !Redirect::where('page_id', $this->id)->exists();
    }
    public function delete() { return $this->getConnection()->transaction(fn() => parent::delete()); }
    protected static function booted(): void
    {
        static::saving(function (Page $page): void {
            if (!preg_match('/^[a-z0-9-]{1,80}$/D', (string) $page->key) || ($page->exists && $page->isDirty(['key','kind']))) { throw ValidationException::withMessages(['key' => 'Keep an existing page key and type unchanged. Use lowercase letters, numbers and hyphens for a new key.']); }
            if (!in_array($page->kind, self::kinds(), true)) { throw ValidationException::withMessages(['kind' => 'Choose a supported page type.']); }
            if ($page->exists && in_array($page->key, self::requiredKeys(), true) && $page->getOriginal('published') && !$page->published) { throw ValidationException::withMessages(['published' => 'Home, Contact and Enquiry remain available for navigation and receipts. Review their content without withdrawing these required pages.']); }
            if ($page->kind === 'policy') { $page->approval_required = true; }
            if ($page->published && !ReleasePolicy::canPublish($page->approval_required, $page->approved_at !== null, $page->reviewedLocales())) {
                throw ValidationException::withMessages(['published' => 'Complete all three translations and required approval before publishing.']);
            }
            if ($page->published) {
                $media = app(MediaReferences::class);
                if (($media->requiredForKind($page->kind) || $page->image_key) && !$media->isAvailable($page->image_key)) { throw ValidationException::withMessages(['image_key' => 'Choose an available main photograph before publishing this page.']); }
            }
        });
        static::deleting(function (Page $page): void {
            $current=static::query()->lockForUpdate()->find($page->id);
            if (!$current || $current->published || in_array($current->key, self::coreKeys(), true) || Vehicle::where('page_id', $current->id)->exists() || Redirect::where('page_id', $current->id)->exists()) { throw ValidationException::withMessages(['page' => 'Unpublish a custom page and remove its vehicle and redirect references before deleting it. Required website pages are retained.']); }
        });
    }
}
