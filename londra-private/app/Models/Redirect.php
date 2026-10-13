<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domain\LocalePolicy;
use Illuminate\Validation\ValidationException;
use App\Services\ContentPathPolicy;
final class Redirect extends Model
{
    protected $fillable = ['locale', 'from_path', 'page_id'];
    public function page(): BelongsTo { return $this->belongsTo(Page::class); }
    protected static function booted(): void
    {
        static::saving(function (Redirect $redirect): void {
            if (!is_string($redirect->from_path) || strlen($redirect->from_path) > 190 || !ContentPathPolicy::isPublicPath($redirect->from_path) || !in_array($redirect->locale, LocalePolicy::LOCALES, true)) { throw ValidationException::withMessages(['from_path' => 'Only local language paths up to 190 characters are allowed. Form-token and verification paths are reserved.']); }
            if ($redirect->from_path === '' || PageTranslation::where('locale',$redirect->locale)->where('slug',$redirect->from_path)->exists()) { throw ValidationException::withMessages(['from_path' => 'Choose an unused non-empty language path. A published or draft page already owns this path.']); }
            if (static::query()->where('locale', $redirect->locale)->where('from_path', $redirect->from_path)->when($redirect->exists, fn($query) => $query->whereKeyNot($redirect->getKey()))->exists()) { throw ValidationException::withMessages(['from_path' => 'Another redirect already uses this path in the selected language.']); }
            if (!PageTranslation::where('page_id',$redirect->page_id)->where('locale',$redirect->locale)->exists()) { throw ValidationException::withMessages(['page_id' => 'The destination page needs a translation in the selected language.']); }
        });
    }
}
