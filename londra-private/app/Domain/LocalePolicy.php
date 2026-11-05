<?php
declare(strict_types=1);
namespace App\Domain;
final class LocalePolicy
{
    public const LOCALES = ['en', 'it', 'es'];

    /**
     * An explicit choice wins, then the saved preference, then the browser's preferred language.
     * English is the default when nothing supported is requested.
     */
    public static function choose(?string $explicit, ?string $saved, ?string $acceptLanguage): string
    {
        foreach ([$explicit, $saved] as $locale) {
            if (in_array($locale, self::LOCALES, true)) {
                return $locale;
            }
        }
        return self::fromAcceptLanguage($acceptLanguage) ?? 'en';
    }

    /** Pick the highest-weighted supported language from an Accept-Language header (RFC 9110). */
    public static function fromAcceptLanguage(?string $header): ?string
    {
        if ($header === null || trim($header) === '' || strlen($header) > 1024) { return null; }
        $best = null; $bestWeight = 0.0; $position = 0;
        foreach (explode(',', $header) as $range) {
            $position++;
            $parts = array_map('trim', explode(';', $range));
            $tag = strtolower($parts[0]);
            if (!preg_match('/^[a-z]{1,8}(?:-[a-z0-9]{1,8})*$/D', $tag)) { continue; }
            $weight = 1.0;
            foreach (array_slice($parts, 1) as $parameter) {
                if (preg_match('/^q\s*=\s*(0(?:\.\d{0,3})?|1(?:\.0{0,3})?)$/Di', $parameter, $match)) { $weight = (float) $match[1]; }
            }
            $primary = explode('-', $tag)[0];
            if ($weight <= 0.0 || !in_array($primary, self::LOCALES, true)) { continue; }
            if ($weight > $bestWeight) { $best = $primary; $bestWeight = $weight; }
        }
        return $best;
    }

    public static function safePath(string $path): bool
    {
        return $path === '' || preg_match('~^[a-z0-9]+(?:[-/][a-z0-9]+)*$~D', $path) === 1;
    }
}
