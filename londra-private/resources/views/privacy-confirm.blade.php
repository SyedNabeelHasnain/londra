@php
$recoveryLocale = $locale;
$recoveryCopy = app(\App\Services\PublicCopy::class)->recovery($locale);
$recoveryAssetVersion = \App\Services\ReleaseUpdater::VERSION;
$recoveryContact = app(\App\Services\PublicContact::class)->recovery();
@endphp
<!doctype html>
<html lang="{{ $locale }}" data-theme="dark">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="color-scheme" content="dark light"><meta name="theme-color" content="#0B0B0B"><title>{{ $recoveryCopy['privacy_title'] }} | Londra Transfers</title><link rel="icon" type="image/svg+xml" href="{{ asset('assets/artwork/favicon.svg') }}?v={{ $recoveryAssetVersion }}"><link rel="alternate icon" type="image/png" sizes="32x32" href="{{ asset('assets/artwork/favicon-32.png') }}?v={{ $recoveryAssetVersion }}"><link rel="apple-touch-icon" sizes="180x180" href="{{ asset('assets/artwork/apple-touch-icon.png') }}?v={{ $recoveryAssetVersion }}"><script src="{{ asset('assets/theme.js') }}?v={{ $recoveryAssetVersion }}"></script><link rel="stylesheet" href="{{ asset('assets/recovery.css') }}?v={{ $recoveryAssetVersion }}"></head>
<body>
@include('errors.header')
<main id="main" class="recovery-main" tabindex="-1"><p class="recovery-eyebrow">Londra Transfers</p><h1>{{ $recoveryCopy['privacy_title'] }}</h1>
@if($valid)
<p>{{ $recoveryCopy['privacy_verify_intro'] }}</p><form method="post" action="/{{ $locale }}/privacy-request/verify/{{ $token }}">@csrf<button class="recovery-button" type="submit">{{ $recoveryCopy['privacy_verify_button'] }}</button></form>
@else
<p>{{ $recoveryCopy['privacy_expired'] }}</p>
@endif
<nav class="recovery-actions" aria-label="{{ $recoveryCopy['privacy_title'] }}">@if($privacy_url)<a class="recovery-link" href="{{ $privacy_url }}">{{ $recoveryCopy['privacy_title'] }}</a>@endif<a class="recovery-link" href="/{{ $locale }}">{{ $recoveryCopy['back_home'] }}</a></nav><div class="recovery-contact"><a href="mailto:{{ $recoveryContact['email'] }}">{{ $recoveryContact['email'] }}</a><a href="tel:{{ $recoveryContact['phone_link'] }}">{{ $recoveryContact['phone_display'] }}</a></div></main>
@include('errors.footer')
</body></html>
