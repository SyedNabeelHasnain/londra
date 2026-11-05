@use('App\Presentation\Html')
@use('App\Presentation\SiteViewModel')
@php
    $kind = $p['kind'];
    $pageVehicle = null;
    foreach ($v['vehicles'] as $candidate) { if ($candidate['page_key'] === $p['key']) { $pageVehicle = $candidate; break; } }
    $serviceContext = $kind === 'service' ? $p['key'] : ($kind === 'airport' ? 'airport-transfers' : ($kind === 'tour' ? 'private-tours' : ''));
    if ($serviceContext !== '' && !isset($cat[$serviceContext])) { $serviceContext = ''; }
    $serviceContextName = $serviceContext !== '' ? $cat[$serviceContext]['title'] : '';
    $paragraphs = array_values(array_filter(preg_split('/\n\s*\n/u', (string) ($p['body'] ?? '')) ?: [], static fn($text) => trim($text) !== ''));
    $isCollection = in_array($kind, ['services', 'fleet', 'airports', 'tours', 'faq', 'directory'], true);
    $isPartnership = $p['key'] === 'partners';
    $breadcrumbs = SiteViewModel::breadcrumbs($v);
@endphp
<nav class="breadcrumb container" aria-label="{{ $u['breadcrumb_label'] }}">
<ol>
@foreach($breadcrumbs as $crumb)
<li>@if($crumb['url'] !== null)<a href="{{ $crumb['url'] }}">{{ $crumb['title'] }}</a>@else<span aria-current="page">{{ $crumb['title'] }}</span>@endif</li>
@endforeach
</ol>
</nav>
@if($kind === 'privacy-request')
@include('public.pages.privacy-request')
@elseif(in_array($kind, ['contact', 'enquiry'], true))
@include('public.pages.contact')
@elseif($kind === 'policy')
@include('public.pages.policy')
@elseif($isCollection)
@include('public.pages.collection')
@else
@include('public.pages.detail')
@endif
