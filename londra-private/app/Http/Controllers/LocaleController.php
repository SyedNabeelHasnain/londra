<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Domain\LocalePolicy;
use App\Models\Page;
use App\Services\CountryResolver;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
final class LocaleController
{
    public function entry(Request $request, CountryResolver $resolver): RedirectResponse
    {
        $preference = $request->cookie('londra_language');
        $country = config('londra.country_lookup') && !in_array($preference, LocalePolicy::LOCALES, true) ? $resolver->country($request->ip()) : null;
        $locale = LocalePolicy::choose(null, $preference, $country);
        return redirect('/'.$locale, 302)->withHeaders(['Cache-Control' => 'private, no-store', 'Vary' => 'Cookie']);
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
