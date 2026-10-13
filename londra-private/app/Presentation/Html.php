<?php
declare(strict_types=1);
namespace App\Presentation;
final class Html
{
    public static function e(mixed $value): string { return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    public static function asset(array $v, string $path): string { return self::versionedUrl($v, $v['asset_base'].$path); }
    public static function image(array $v, ?string $key, string $class = '', bool $priority = false, ?string $sizes = null): string
    {
        $source = self::imageSource($v, $key);
        if ($source === null) { return ''; }
        $media = $v['media'][$key];
        $width = (int) ($media['width'] ?? 0); $height = (int) ($media['height'] ?? 0);
        $dimensions = $width > 0 && $height > 0 ? ' width="'.$width.'" height="'.$height.'"' : '';
        return '<img class="'.self::e($class).'" src="'.self::e($source['src']).'"'.($source['srcset'] ? ' srcset="'.self::e(implode(', ', $source['srcset'])).'" sizes="'.self::e($sizes ?? ($priority ? '100vw' : '(min-width: 1100px) 50vw, 100vw')).'"' : '').' alt="'.self::e($media['alt'][$v['locale']] ?? '').'"'.$dimensions.' decoding="async" '.($priority ? 'fetchpriority="high"' : 'loading="lazy"').'>';
    }
    public static function imageUrl(array $v, ?string $key): ?string
    {
        $source = self::imageSource($v, $key);
        if ($source === null) { return null; }
        $url = $source['src'];
        return str_starts_with($url, 'https://') || str_starts_with($url, 'http://') ? $url : rtrim($v['origin'], '/').'/'.ltrim($url, '/');
    }
    private static function imageSource(array $v, ?string $key): ?array
    {
        if ($key === null || $key === '') { return null; }
        $media = $v['media'][$key] ?? null;
        if (!is_array($media)) { return null; }
        $assetBase = $v['asset_base'];
        $rawVariants = $media['variants'] ?? [];
        $variants = is_array($rawVariants) ? array_values(array_filter($rawVariants, static fn($variant) => is_array($variant) && is_string($variant['path'] ?? null) && $variant['path'] !== '' && (int) ($variant['width'] ?? 0) > 0)) : [];
        usort($variants, static fn($a, $b) => (int) $a['width'] <=> (int) $b['width']);
        $srcset = [];
        foreach ($variants as $variant) { $srcset[] = self::versionedUrl($v, $assetBase.$variant['path']).' '.(int) $variant['width'].'w'; }
        $last = $variants ? end($variants) : null;
        $original = $media['original'] ?? null;
        $src = $last ? $assetBase.$last['path'] : ($media['url'] ?? (is_string($original) && $original !== '' ? $assetBase.$original : ''));
        if (!is_string($src) || $src === '') { return null; }
        return ['src'=>self::versionedUrl($v, $src), 'srcset'=>$srcset];
    }
    private static function versionedUrl(array $v, string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || !preg_match('~^/?assets/~', $path) || empty($v['asset_version'])) { return $url; }
        return $url.(str_contains($url, '?') ? '&' : '?').'v='.rawurlencode($v['asset_version']);
    }
    public static function arrow(): string { return '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="none"><path d="M4 12h15m-6-6 6 6-6 6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>'; }
}
