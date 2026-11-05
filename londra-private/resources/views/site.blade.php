@use('App\Presentation\Html')
@use('App\Presentation\SiteViewModel')
@php
    $u = $v['u']; $p = $v['page']; $links = $v['links']; $cat = $v['catalog']; $contact = $v['contact']; $contact['phone_display'] = str_replace(' ', "\u{00A0}", $contact['phone_display']);
    if (!empty($p['image_alt']) && !empty($p['image_key']) && isset($v['media'][$p['image_key']])) { $v['media'][$p['image_key']]['alt'][$v['locale']] = $p['image_alt']; }
    $enquiryUrl = $links['enquiry'] ?? $links['contact'] ?? 'tel:'.$contact['phone_link'];
    $whatsapp = 'https://wa.me/'.ltrim($contact['phone_link'], '+');
    $instagram = 'https://www.instagram.com/'.$contact['instagram'].'/';
    $ogLocale = ['en' => 'en_GB', 'it' => 'it_IT', 'es' => 'es_ES'][$v['locale']];
    $sharingImage = Html::imageUrl($v, $p['image_key']);
    $clientCopy = array_intersect_key($u, array_flip(['light', 'gallery_title', 'invalid_email', 'too_long', 'required', 'sending', 'try_again', 'received', 'reference', 'form_expired', 'form_refreshed', 'form_refresh_failed', 'forms_unavailable', 'retry_after', 'enquiry_form_used']));
    $showInvitation = !in_array($p['kind'], SiteViewModel::WITHOUT_INVITATION, true);
@endphp
<!DOCTYPE html>
<html lang="{{ $v['locale'] }}" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="dark light">
<meta name="theme-color" content="#0B0B0B">
<title>{{ $p['seo_title'] }}</title>
<meta name="description" content="{{ $p['meta_description'] }}">
<meta name="robots" content="{{ $v['indexable'] ? 'index,follow,max-image-preview:large' : 'noindex,nofollow,noarchive' }}">
<link rel="canonical" href="{{ $v['canonical'] }}">
@foreach($v['alternates'] as $language => $url)
<link rel="alternate" hreflang="{{ $language }}" href="{{ $url }}">
@endforeach
<link rel="alternate" hreflang="x-default" href="{{ $p['key'] === 'home' ? $v['origin'].'/' : ($v['alternates']['en'] ?? $v['canonical']) }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Londra Transfers">
<meta property="og:title" content="{{ $p['seo_title'] }}">
<meta property="og:description" content="{{ $p['meta_description'] }}">
<meta property="og:url" content="{{ $v['canonical'] }}">
<meta property="og:locale" content="{{ $ogLocale }}">
@if($sharingImage !== null)
<meta property="og:image" content="{{ $sharingImage }}">
<meta property="og:image:alt" content="{{ $v['media'][$p['image_key']]['alt'][$v['locale']] ?? '' }}">
@endif
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" type="image/svg+xml" href="{{ Html::asset($v, 'assets/artwork/favicon.svg') }}">
<link rel="alternate icon" type="image/png" sizes="32x32" href="{{ Html::asset($v, 'assets/artwork/favicon-32.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ Html::asset($v, 'assets/artwork/apple-touch-icon.png') }}">
<script src="{{ Html::asset($v, 'assets/theme.js') }}"></script>
<link rel="stylesheet" href="{{ $v['css_url'] }}">
<script {!! $v['offline'] ? 'defer' : 'type="module"' !!} src="{{ $v['js_url'] }}"></script>
<script type="application/ld+json">{!! $v['schema'] !!}</script>
</head>
<body data-page="{{ $p['key'] }}" data-kind="{{ $p['kind'] }}" data-ui="{{ json_encode($clientCopy, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}">
<a class="skip-link" href="#main">{{ $u['skip'] }}</a>
@include('public.header')
<main id="main" tabindex="-1">
@if($p['kind'] === 'home')
@include('public.home')
@else
@include('public.page')
@endif
@if($showInvitation)
@include('public.invitation')
@endif
</main>
@include('public.footer')
@include('public.dialogs')
</body>
</html>
