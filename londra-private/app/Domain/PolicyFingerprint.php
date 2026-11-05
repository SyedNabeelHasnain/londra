<?php
declare(strict_types=1);
namespace App\Domain;
/** Identifies the exact published wording of a policy translation, so an approval applies only to the text that was reviewed. */
final class PolicyFingerprint
{
    public const FIELDS = ['slug', 'title', 'eyebrow', 'intro', 'body', 'seo_title', 'meta_description'];
    public static function of(array $translation): string
    {
        $values = array_map(static fn(string $field): string => (string) ($translation[$field] ?? ''), self::FIELDS);
        return hash('sha256', json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
