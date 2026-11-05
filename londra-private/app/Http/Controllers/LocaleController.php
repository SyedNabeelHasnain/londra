<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Domain\LocalePolicy;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
final class LocaleController
{
    public function entry(Request $request): RedirectResponse
    {
        $preference = $request->cookie('londra_language');
        $locale = LocalePolicy::choose(null, is_string($preference) ? $preference : null, $request->header('Accept-Language'));
        return redirect('/'.$locale, 302)->withHeaders(['Cache-Control' => 'private, no-store', 'Vary' => 'Cookie, Accept-Language']);
    }
    public function choose(Request $request, string $locale, string $key): RedirectResponse
    {
        abort_unless(in_array($locale, LocalePolicy::LOCALES, true), 404);
        $page = Page::published()->with('translations')->where('key', $key)->firstOrFail();
        $translation = $page->translation($locale);
        abort_unless($translation && $translation->ready, 404);
        return redirect($translation->url(), 303)->cookie('londra_language', $locale, 43200, '/', null, $request->isSecure(), true, false, 'lax');
    }
}
