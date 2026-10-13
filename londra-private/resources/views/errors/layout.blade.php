@php
$pathLocale = strtolower((string) request()->segment(1));
$recoveryLocale = in_array($pathLocale, ['en','it','es'], true) ? $pathLocale : (in_array(app()->getLocale(), ['en','it','es'], true) ? app()->getLocale() : 'en');
$recoveryCopy = app(\App\Services\PublicCopy::class)->recovery($recoveryLocale);
$recoveryAssetVersion = \App\Services\ReleaseUpdater::VERSION;
$recoveryContact = app(\App\Services\PublicContact::class)->recovery();
@endphp
<!doctype html>
<html lang="{{ $recoveryLocale }}" data-theme="dark">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="color-scheme" content="dark light"><meta name="theme-color" content="#0B0B0B"><title>{{ $recoveryCopy['error_'.$statusCode.'_title'] }} | Londra Transfers</title><link rel="icon" type="image/svg+xml" href="{{ asset('assets/artwork/favicon.svg') }}?v={{ $recoveryAssetVersion }}"><link rel="alternate icon" type="image/png" sizes="32x32" href="{{ asset('assets/artwork/favicon-32.png') }}?v={{ $recoveryAssetVersion }}"><link rel="apple-touch-icon" sizes="180x180" href="{{ asset('assets/artwork/apple-touch-icon.png') }}?v={{ $recoveryAssetVersion }}"><script src="{{ asset('assets/theme.js') }}?v={{ $recoveryAssetVersion }}"></script><link rel="stylesheet" href="{{ asset('assets/recovery.css') }}?v={{ $recoveryAssetVersion }}"></head>
<body>
@include('errors.header')
<main id="main" class="recovery-main" tabindex="-1"><p class="recovery-eyebrow">Londra Transfers</p><h1>{{ $recoveryCopy['error_'.$statusCode.'_title'] }}</h1><p>{{ $recoveryCopy['error_'.$statusCode.'_body'] }}</p><nav class="recovery-actions" aria-label="{{ $recoveryCopy['home'] }}">@if($statusCode === 419 && !empty($recovery_url))<a class="recovery-button" href="{{ $recovery_url }}">{{ $recoveryCopy['reload_form'] }}</a><a class="recovery-link" href="/{{ $recoveryLocale }}">{{ $recoveryCopy['back_home'] }}</a>@else<a class="recovery-button" href="/{{ $recoveryLocale }}">{{ $recoveryCopy['back_home'] }}</a>@endif</nav><div class="recovery-contact"><a href="tel:{{ $recoveryContact['phone_link'] }}">{{ $recoveryContact['phone_display'] }}</a><a href="mailto:{{ $recoveryContact['email'] }}">{{ $recoveryContact['email'] }}</a><a href="https://wa.me/{{ ltrim($recoveryContact['phone_link'], '+') }}" rel="noopener noreferrer">WhatsApp</a></div></main>
@include('errors.footer')
</body></html>
