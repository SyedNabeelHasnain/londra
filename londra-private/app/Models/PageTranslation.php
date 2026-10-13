<?php
declare(strict_types=1);
namespace App\Models;
use App\Domain\LocalePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use App\Services\ContentPathPolicy;
final class PageTranslation extends Model
{
    protected $fillable = ['locale', 'slug', 'title', 'eyebrow', 'intro', 'detail_heading', 'body', 'highlights', 'faq_items', 'seo_title', 'meta_description', 'image_alt', 'ready'];
    protected function casts(): array { return ['highlights' => 'array', 'faq_items' => 'array', 'ready' => 'boolean']; }
    public function save(array $options = []): bool { return $this->getConnection()->transaction(fn(): bool => parent::save($options)); }
    public function page(): BelongsTo { return $this->belongsTo(Page::class); }
    public function url(): string { return rtrim(config('app.url'), '/').'/'.$this->locale.($this->slug === '' ? '' : '/'.$this->slug); }
    public function hasReviewContent(): bool
    {
        $page=$this->page;
        if(!$page||!in_array($this->locale,LocalePolicy::LOCALES,true)||!is_string($this->slug)||strlen($this->slug)>190||!ContentPathPolicy::isPublicPath($this->slug)||(($page->key==='home')!==($this->slug===''))){return false;}
        foreach(['title','intro','seo_title','meta_description'] as $field){if(trim((string)$this->{$field})===''){return false;}}
        if(in_array($page->kind,['service','vehicle','airport','tour','editorial','policy'],true)&&trim((string)$this->body)===''){return false;}
        if($page->kind==='faq'){
            $items=$this->faq_items??[];
            if($items===[]||collect($items)->contains(fn($item)=>!is_array($item)||trim((string)($item['q']??''))===''||trim((string)($item['a']??''))==='')){return false;}
        }
        return true;
    }
    protected static function booted(): void
    {
        static::saving(function (PageTranslation $translation): void {
            $page = $translation->page()->lockForUpdate()->first();
            if (!$page) { throw ValidationException::withMessages(['page_id' => 'Attach this translation to an existing page.']); }
            $translation->setRelation('page', $page);
            if ($page->key === 'home' && $translation->slug === null) { $translation->slug = ''; }
            if (!in_array($translation->locale, LocalePolicy::LOCALES, true) || !is_string($translation->slug) || strlen($translation->slug) > 190 || !ContentPathPolicy::isPublicPath($translation->slug)) {
                throw ValidationException::withMessages(['slug' => 'Use a valid localised path with lowercase letters, numbers, hyphens and slashes. Form-token and verification paths are reserved.']);
            }
            if (($page->key === 'home') !== ($translation->slug === '')) {
                throw ValidationException::withMessages(['slug' => 'The homepage must use the empty path; all other pages need a path.']);
            }
            if ($translation->exists && $translation->isDirty('locale')) {
                throw ValidationException::withMessages(['locale' => 'Keep this translation in its existing language.']);
            }
            if (static::query()->where('locale', $translation->locale)->where('slug', $translation->slug)->when($translation->exists, fn($query) => $query->whereKeyNot($translation->getKey()))->exists()) {
                throw ValidationException::withMessages(['slug' => 'Another page already uses this path in the selected language.']);
            }
            if (Redirect::query()->where('locale', $translation->locale)->where('from_path', $translation->slug)->where('page_id', '!=', $translation->page_id)->exists()) {
                throw ValidationException::withMessages(['slug' => 'A redirect to another page already owns this path in the selected language.']);
            }
            if ($translation->ready && (trim((string) $translation->title) === '' || trim((string) $translation->intro) === '' || trim((string) $translation->seo_title) === '' || trim((string) $translation->meta_description) === '')) {
                throw ValidationException::withMessages(['ready' => 'Title, introduction and metadata are required.']);
            }
            if ($translation->ready && in_array($page->kind, ['service','vehicle','airport','tour','editorial','policy'], true) && trim((string) $translation->body) === '') { throw ValidationException::withMessages(['body' => 'Complete the main text before marking this detail or policy translation reviewed.']); }
            if ($translation->ready && $page->kind === 'faq') {
                $items = $translation->faq_items ?? [];
                if ($items === [] || collect($items)->contains(fn($item) => !is_array($item) || trim((string) ($item['q'] ?? '')) === '' || trim((string) ($item['a'] ?? '')) === '')) { throw ValidationException::withMessages(['faq_items' => 'Provide at least one complete question and answer before marking this FAQ translation reviewed.']); }
            }
            if (!$translation->ready && $page->published && in_array($page->key, Page::requiredKeys(), true)) { throw ValidationException::withMessages(['ready' => 'Keep the published Home, Contact and Enquiry translations reviewed. These required pages remain available for navigation and receipts.']); }
        });
        static::updating(function (PageTranslation $translation): void {
            if ($translation->page()->where('kind', 'policy')->exists()) {
                Page::query()->whereKey($translation->page_id)->update(['published' => false, 'approved_at' => null]);
            } elseif ($translation->isDirty('ready') && !$translation->ready) {
                Page::query()->whereKey($translation->page_id)->update(['published' => false]);
            }
            ContentRevision::create(['page_id' => $translation->page_id, 'locale' => $translation->locale, 'snapshot' => $translation->getOriginal(), 'user_id' => auth()->id()]);
        });
        static::updated(function (PageTranslation $translation): void {
            if ($translation->wasChanged('slug')&&!ContentPathPolicy::isReserved($translation->getOriginal('slug'))) {
                $redirect = Redirect::query()->firstOrNew(['locale' => $translation->locale, 'from_path' => $translation->getOriginal('slug')]);
                if ($redirect->exists && (string) $redirect->page_id !== (string) $translation->page_id) { throw ValidationException::withMessages(['slug' => 'The previous path has a redirect to another page. Resolve that conflicting redirect before changing this path.']); }
                $redirect->page_id = $translation->page_id;
                $redirect->save();
            }
        });
        static::saved(function (PageTranslation $translation): void {
            Redirect::query()->where('locale', $translation->locale)->where('from_path', $translation->slug)->where('page_id', $translation->page_id)->delete();
        });
    }
}
