<?php
declare(strict_types=1);
namespace App\Services;

final class FleetPhotoGallery
{
    // The source list records appearance approvals only, never an inferred publication licence.
    public const APPROVED_REFERENCES = [
        'e-class' => ['E01','E02','E03','E04','E05'],
        's-class' => ['S01','S02','S03','S04','S05','S06','S07'],
        'v-class' => ['V01','V02','V03','V04','V05','V06','V07'],
        'vito' => ['T01','T02','T03','T04','T05'],
    ];

    public static function imageKey(string $code, string $reference): string
    {
        return 'fleet-'.$code.'-'.strtolower($reference);
    }

    public static function allKeys(string $code): array
    {
        return array_map(static fn (string $reference) => self::imageKey($code, $reference), self::APPROVED_REFERENCES[$code] ?? []);
    }

    /**
     * Return only an entire, explicitly cleared, uploaded gallery.
     * Nothing in the reference shortlist itself is authorization to publish.
     * The caller passes image metadata from the existing MediaAsset CMS records.
     */
    public static function visible(string $code, array $settings, array $media, string $locale = 'en'): array
    {
        $approved = self::APPROVED_REFERENCES[$code] ?? [];
        if ($approved === []) { return []; }
        $state = $settings[$code] ?? null;
        if (!is_array($state) || ($state['rights_confirmed'] ?? false) !== true) { return []; }
        if (mb_strlen(trim((string) ($state['rights_note'] ?? ''))) < 20) { return []; }
        $keys = $state['photos'] ?? [];
        $expected = self::allKeys($code);
        if (!is_array($keys) || count($keys) !== count($expected) || count(array_unique($keys)) !== count($expected) || array_diff($expected, $keys) || array_diff($keys, $expected)) {
            return [];
        }
        $gallery = [];
        foreach ($approved as $reference) {
            $key = self::imageKey($code, $reference);
            $item = $media[$key] ?? null;
            if (!is_array($item) || empty($item['url']) || empty($item['width']) || empty($item['height'])) {
                return []; // Never show a partial gallery as a completed one.
            }
            $alt = (string) ($item['alt'][$locale] ?? $item['alt']['en'] ?? '');
            if (trim($alt) === '') { return []; }
            $gallery[] = ['reference'=>$reference,'key'=>$key,'alt'=>$alt,'url'=>(string)$item['url']];
        }
        return $gallery;
    }

    public static function referencesFor(string $key, array $settings): array
    {
        $references = [];
        foreach (self::APPROVED_REFERENCES as $code => $ids) {
            if (in_array($key, (array) ($settings[$code]['photos'] ?? []), true)) {
                $references[] = 'Fleet photograph gallery: '.$code;
            }
        }
        return $references;
    }
}
