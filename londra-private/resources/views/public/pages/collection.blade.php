@use('App\Presentation\Html')
@use('App\Presentation\SiteViewModel')
<section class="page-intro" aria-labelledby="page-title">
<div class="container page-intro-grid">
<div><p class="eyebrow">{{ $p['eyebrow'] ?: $u['signature'] }}</p><h1 id="page-title">{{ $p['title'] }}</h1></div>
<div class="page-intro-text">
<p class="lede">{{ $p['intro'] }}</p>
@foreach($paragraphs as $paragraph)
<p class="collection-note">{{ $paragraph }}</p>
@endforeach
</div>
</div>
</section>
@if($kind === 'services' || $kind === 'tours')
@php $spreadItems = SiteViewModel::ofKind($v, $kind === 'services' ? 'service' : 'tour'); @endphp
@if($spreadItems)
<section class="section collection-section">
<div class="container">
@if($kind === 'services')
<nav class="chip-nav" aria-label="{{ $p['title'] }}"><ul>
@foreach($spreadItems as $item)
<li><a href="#service-{{ $item['key'] }}">{{ $item['title'] }}</a></li>
@endforeach
</ul></nav>
@endif
<div class="spread-list">
@foreach($spreadItems as $item)
@php
    $spreadBody = trim((preg_split('/\n\s*\n/u', (string) ($item['body'] ?? '')) ?: [])[0] ?? '');
    $spreadImage = Html::image($v, $item['image_key'], '', 'lazy', '(min-width: 1200px) 620px, (min-width: 900px) 48vw, 100vw');
@endphp
<article class="spread {{ $spreadImage ? '' : 'is-text-only' }}" id="service-{{ $item['key'] }}">
<div class="spread-copy">
<p class="eyebrow">{{ $item['eyebrow'] ?: $u['signature'] }}</p>
<h2><a href="{{ $item['url'] }}">{{ $item['title'] }}</a></h2>
<p class="spread-intro">{{ $item['intro'] }}</p>
@if($spreadBody !== '' && $spreadBody !== $item['intro'])<p>{{ $spreadBody }}</p>@endif
@if($item['highlights'])
<ul class="spread-highlights">
@foreach(array_slice($item['highlights'], 0, 4) as $highlight)
<li>{{ $highlight }}</li>
@endforeach
</ul>
@endif
<a class="text-link" href="{{ $item['url'] }}"><span>{{ str_replace(':title', $item['title'], $u['explore_named']) }}</span>{!! Html::arrow() !!}</a>
</div>
@if($spreadImage)<a class="spread-media media" href="{{ $item['url'] }}" tabindex="-1" aria-hidden="true">{!! $spreadImage !!}</a>@endif
</article>
@endforeach
</div>
</div>
</section>
@endif
@elseif($kind === 'fleet')
<section class="section band fleet-section fleet-index">
<div class="container">
@include('public.fleet', ['fleetId' => 'fleet-page', 'fleetHeading' => 'h2', 'fleetNotes' => false])
</div>
</section>
@elseif($kind === 'airports')
@php $airportItems = SiteViewModel::ofKind($v, 'airport'); @endphp
<section class="section collection-section">
<div class="container">
<div class="airport-grid">
@foreach($airportItems as $item)
<a class="airport-card" href="{{ $item['url'] }}">
<span class="eyebrow">{{ $item['eyebrow'] }}</span>
<h2>{{ $item['title'] }}</h2>
<p>{{ $item['intro'] }}</p>
<span class="text-link"><span>{{ str_replace(':title', $item['title'], $u['explore_named']) }}</span>{!! Html::arrow() !!}</span>
</a>
@endforeach
</div>
@isset($links['private-aviation'])
<div class="collection-footer"><a class="text-link" href="{{ $links['private-aviation'] }}"><span>{{ $cat['private-aviation']['title'] }}</span>{!! Html::arrow() !!}</a></div>
@endisset
</div>
</section>
@elseif($kind === 'faq')
<section class="section collection-section">
<div class="container faq-layout faq-page">
@include('public.faq-list', ['faqItems' => $p['faq_items'] ?? [], 'faqHeading' => 'h2'])
<aside class="journey-card" aria-labelledby="faq-help-title">
<p class="eyebrow">{{ $u['contact_kicker'] }}</p>
<h2 id="faq-help-title">{{ $u['faq_help_title'] }}</h2>
<p>{{ $u['faq_help_text'] }}</p>
<a class="button" href="{{ $enquiryUrl }}" data-open-enquiry data-service="" data-service-name=""><span>{{ $u['enquire'] }}</span>{!! Html::arrow() !!}</a>
@isset($links['contact'])<a class="text-link" href="{{ $links['contact'] }}"><span>{{ $u['contact'] }}</span>{!! Html::arrow() !!}</a>@endisset
</aside>
</div>
</section>
@elseif($kind === 'directory')
<section class="section collection-section">
<div class="container directory-grid">
@foreach(SiteViewModel::navigation($v) as $group)
<div class="directory-group">
<h2>{{ $group['label'] }}</h2>
<ul>
@if($group['overview'])
<li><a href="{{ $group['overview']['url'] }}"><span>{{ $group['overview']['title'] }}</span>{!! Html::arrow() !!}</a></li>
@endif
@foreach($group['items'] as $item)
<li><a href="{{ $item['url'] }}"><span>{{ $item['title'] }}</span>{!! Html::arrow() !!}</a></li>
@endforeach
</ul>
</div>
@endforeach
</div>
</section>
@endif
