<?php
declare(strict_types=1);
namespace App\Presentation;

final class Html
{
    private const ICONS = [
        'arrow' => '<path d="M4 12h15m-6-6 6 6-6 6"/>',
        'back' => '<path d="M20 12H5m6-6-6 6 6 6"/>',
        'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'chevron' => '<path d="m7 10 5 5 5-5"/>',
        'up' => '<path d="M12 20V5m-6 6 6-6 6 6"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4m0-14.2-1.4 1.4M6.3 17.7l-1.4 1.4"/>',
        'moon' => '<path d="M20.5 13.2A8.5 8.5 0 0 1 10.8 3.5a8.5 8.5 0 1 0 9.7 9.7Z"/>',
        'person' => '<circle cx="12" cy="7.5" r="3.5"/><path d="M5 21v-1.5a7 7 0 0 1 14 0V21"/>',
        'phone' => '<path d="M5.6 3h3l1.6 4.4-2.1 1.4a12 12 0 0 0 7.1 7.1l1.4-2.1L21 15.4v3a2.6 2.6 0 0 1-2.8 2.6A17.4 17.4 0 0 1 3 5.8 2.6 2.6 0 0 1 5.6 3Z"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="1.5"/><path d="m3.5 6 8.5 7 8.5-7"/>',
        'chat' => '<path d="M20 11.5a8 8 0 0 1-11.8 7L4 20l1.5-4.1A8 8 0 1 1 20 11.5Z"/>',
        'pin' => '<path d="M12 21s-6.5-6.2-6.5-11.2a6.5 6.5 0 0 1 13 0C18.5 14.8 12 21 12 21Z"/><circle cx="12" cy="9.8" r="2.3"/>',
        'expand' => '<path d="M14 4h6v6M10 20H4v-6m16-10-6.5 6.5M4 20l6.5-6.5"/>',
        'instagram' => '<rect x="3.5" y="3.5" width="17" height="17" rx="4.5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r=".6" fill="currentColor"/>',
    ];

    public static function e(mixed $value): string { return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

    public static function icon(string $name, int $size = 20): string
    {
        if (!isset(self::ICONS[$name])) { throw new \InvalidArgumentException('Unsupported interface icon.'); }
        return '<svg class="icon icon-'.$name.'" viewBox="0 0 24 24" width="'.$size.'" height="'.$size.'" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'.self::ICONS[$name].'</svg>';
    }

    public static function arrow(): string { return self::icon('arrow'); }

    public static function asset(array $v, string $path): string { return self::versionedUrl($v, $v['asset_base'].$path); }

    /**
     * Render a responsive photograph. $loading is "eager-high" for the single LCP image, "lazy" for
     * standard below-the-fold images and "hidden" for images that are only revealed after interaction.
     */
    public static function image(array $v, ?string $key, string $class = '', bool|string $loading = 'lazy', ?string $sizes = null): string
    {
        $source = self::imageSource($v, $key);
        if ($source === null) { return ''; }
        if ($loading === true) { $loading = 'eager-high'; } elseif ($loading === false) { $loading = 'lazy'; }
        $media = $v['media'][$key];
        $width = (int) ($media['width'] ?? 0); $height = (int) ($media['height'] ?? 0);
        $dimensions = $width > 0 && $height > 0 ? ' width="'.$width.'" height="'.$height.'"' : '';
        $priority = match ($loading) { 'eager-high' => ' fetchpriority="high"', 'hidden' => ' loading="lazy" fetchpriority="low"', default => ' loading="lazy"' };
        $srcset = $source['srcset'] ? ' srcset="'.self::e(implode(', ', $source['srcset'])).'" sizes="'.self::e($sizes ?? '(min-width: 1100px) 50vw, 100vw').'"' : '';
        return '<img'.($class !== '' ? ' class="'.self::e($class).'"' : '').' src="'.self::e($source['src']).'"'.$srcset.' alt="'.self::e($media['alt'][$v['locale']] ?? '').'"'.$dimensions.' decoding="async"'.$priority.'>';
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
        return ['src' => self::versionedUrl($v, $src), 'srcset' => $srcset];
    }

    private static function versionedUrl(array $v, string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || !preg_match('~^/?assets/~', $path) || empty($v['asset_version'])) { return $url; }
        return $url.(str_contains($url, '?') ? '&' : '?').'v='.rawurlencode($v['asset_version']);
    }
}
