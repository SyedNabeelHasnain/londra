<?php
declare(strict_types=1);
namespace App\Domain;
final class LocalePolicy
{
    public const LOCALES = ['en', 'it', 'es'];
    public static function choose(?string $explicit, ?string $saved, ?string $country): string
    {
        foreach ([$explicit, $saved] as $locale) {
            if (in_array($locale, self::LOCALES, true)) {
                return $locale;
            }
        }
        return match (strtoupper($country ?? '')) { 'IT' => 'it', 'ES' => 'es', default => 'en' };
    }
    public static function safePath(string $path): bool
    {
        return $path === '' || preg_match('~^[a-z0-9]+(?:[-/][a-z0-9]+)*$~D', $path) === 1;
    }
}
