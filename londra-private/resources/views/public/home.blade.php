@use('App\Presentation\Html')
@use('App\Presentation\SiteViewModel')
@php
    $homeHero = Html::image($v, $p['image_key'], '', 'eager-high', '100vw');
    $homeParagraphs = array_values(array_filter(preg_split('/\n\s*\n/u', (string) ($p['body'] ?? '')) ?: [], static fn($text) => trim($text) !== ''));
    $homeServices = SiteViewModel::ofKind($v, 'service');
    $featuredKeys = ['airport-transfers', 'private-transfers', 'chauffeur-service'];
    $featured = array_values(array_filter($homeServices, static fn(array $item) => in_array($item['key'], $featuredKeys, true)));
    if (!$featured) { $featured = array_slice($homeServices, 0, 3); }
    $featuredIndex = array_column($featured, 'key');
    $moreServices = array_values(array_filter($homeServices, static fn(array $item) => !in_array($item['key'], $featuredIndex, true)));
    $homeAirports = SiteViewModel::ofKind($v, 'airport');
    $homeFleet = SiteViewModel::fleet($v);
    $homeFacts = SiteViewModel::facts($v);
    $faqPage = $cat['faqs'] ?? null;
@endphp
<section class="hero {{ $homeHero ? '' : 'is-text-only' }}" aria-labelledby="home-title">
@if($homeHero)
<div class="hero-media">{!! $homeHero !!}</div>
@endif
<div class="container hero-inner">
<div class="hero-copy">
<p class="eyebrow">{{ $p['eyebrow'] }}</p>
<h1 class="display" id="home-title">{{ $p['title'] }}</h1>
<p class="hero-intro">{{ $p['intro'] }}</p>
<div class="actions">
<a class="button" href="{{ $enquiryUrl }}" data-open-enquiry data-service="" data-service-name=""><span>{{ $u['enquire'] }}</span>{!! Html::arrow() !!}</a>
@isset($links['services'])
<a class="button button-ghost" href="{{ $links['services'] }}"><span>{{ $u['all_services'] }}</span>{!! Html::arrow() !!}</a>
@endisset
</div>
</div>
@if($homeFacts)
<ul class="hero-facts">
@foreach($homeFacts as $fact)
<li><strong>{{ $fact['value'] }}</strong><span>{{ $fact['label'] }}</span></li>
@endforeach
</ul>
@endif
</div>
</section>

@if($homeParagraphs)
<section class="section intro-section" aria-labelledby="intro-title">
<div class="container intro-grid">
<div><p class="eyebrow">{{ $u['about'] }}</p><h2 class="intro-statement" id="intro-title">{{ $u['intro_title'] }}</h2></div>
<div class="intro-body">
@foreach($homeParagraphs as $paragraph)
<p>{{ $paragraph }}</p>
@endforeach
@isset($links['about'])
<a class="text-link" href="{{ $links['about'] }}"><span>{{ $u['experience_link'] }}</span>{!! Html::arrow() !!}</a>
@endisset
</div>
</div>
</section>
@endif

@if($featured)
<section class="section services-section" id="services" aria-labelledby="services-title">
<div class="container">
@include('public.section-heading', ['headingKicker' => $u['services_kicker'], 'headingTitle' => $u['services_title'], 'headingIntro' => $u['services_intro'], 'headingId' => 'services-title'])
<div class="card-grid">
@foreach($featured as $item)
@php $cardImage = Html::image($v, $item['image_key'], '', 'lazy', '(min-width: 1200px) 410px, (min-width: 720px) 45vw, 100vw'); @endphp
<article class="service-card {{ $cardImage ? '' : 'is-text-only' }}">
@if($cardImage)<a class="service-card-media media" href="{{ $item['url'] }}" tabindex="-1" aria-hidden="true">{!! $cardImage !!}</a>@endif
<div class="service-card-body">
<h3><a href="{{ $item['url'] }}">{{ $item['title'] }}</a></h3>
<p>{{ $item['intro'] }}</p>
<a class="text-link" href="{{ $item['url'] }}"><span>{{ $u['discover_more'] }}<span class="visually-hidden">: {{ $item['title'] }}</span></span>{!! Html::arrow() !!}</a>
</div>
</article>
@endforeach
</div>
@if($moreServices)
<div class="more-services">
<p class="more-services-title">{{ $u['services_more'] }}</p>
<ul class="link-grid">
@foreach($moreServices as $item)
<li><a href="{{ $item['url'] }}"><span>{{ $item['title'] }}</span>{!! Html::arrow() !!}</a></li>
@endforeach
</ul>
</div>
@endif
</div>
</section>
@endif

@if($homeAirports)
@php $arrivalsImage = Html::image($v, 'airport-transfer', '', 'lazy', '(min-width: 1200px) 620px, (min-width: 900px) 48vw, 100vw'); @endphp
<section class="section split-section arrivals-section {{ $arrivalsImage ? '' : 'is-text-only' }}" id="arrivals" aria-labelledby="airports-title">
<div class="container split">
<div class="split-copy">
<p class="eyebrow">{{ $u['l4_airports_label'] }}</p>
<h2 id="airports-title">{{ $u['airport_title'] }}</h2>
<p class="lede">{{ $u['airport_intro'] }}</p>
<div class="airport-links">
@foreach($homeAirports as $item)
<a href="{{ $item['url'] }}">{{ $item['title'] }}{!! Html::arrow() !!}</a>
@endforeach
</div>
<div class="actions">
@isset($links['airports'])<a class="text-link" href="{{ $links['airports'] }}"><span>{{ $u['all_airports'] }}</span>{!! Html::arrow() !!}</a>@endisset
@isset($links['private-aviation'])<a class="text-link" href="{{ $links['private-aviation'] }}"><span>{{ $cat['private-aviation']['title'] }}</span>{!! Html::arrow() !!}</a>@endisset
</div>
</div>
@if($arrivalsImage)<figure class="split-media media">{!! $arrivalsImage !!}</figure>@endif
</div>
</section>
@endif

@if($homeFleet)
<section class="section band fleet-section" id="fleet" aria-labelledby="fleet-title">
<div class="container">
@include('public.section-heading', ['headingKicker' => $u['fleet_kicker'], 'headingTitle' => $u['fleet_title'], 'headingIntro' => $u['fleet_intro'], 'headingId' => 'fleet-title'])
@include('public.fleet', ['fleetId' => 'home-fleet', 'fleetHeading' => 'h3'])
</div>
</section>
@endif

@php $experienceImage = Html::image($v, 'chauffeur-detail', '', 'lazy', '(min-width: 1200px) 620px, (min-width: 900px) 48vw, 100vw'); @endphp
<section class="section split-section experience-section {{ $experienceImage ? '' : 'is-text-only' }}" aria-labelledby="experience-title">
<div class="container split split-reverse">
<div class="split-copy">
<p class="eyebrow">{{ $u['experience_kicker'] }}</p>
<h2 id="experience-title">{{ $u['experience_title'] }}</h2>
<p class="lede">{{ $u['experience_body'] }}</p>
<dl class="principles">
<div><dt>{{ $u['comfort_word'] }}</dt><dd>{{ $u['value_comfort'] }}</dd></div>
<div><dt>{{ $u['punctuality_word'] }}</dt><dd>{{ $u['value_punctuality'] }}</dd></div>
<div><dt>{{ $u['discretion_word'] }}</dt><dd>{{ $u['value_discretion'] }}</dd></div>
</dl>
@isset($links['about'])<a class="text-link" href="{{ $links['about'] }}"><span>{{ $u['experience_link'] }}</span>{!! Html::arrow() !!}</a>@endisset
</div>
@if($experienceImage)<figure class="split-media media">{!! $experienceImage !!}</figure>@endif
</div>
</section>

<section class="section process-section" aria-labelledby="process-title">
<div class="container">
@include('public.section-heading', ['headingKicker' => $u['process_kicker'], 'headingTitle' => $u['process_title'], 'headingIntro' => '', 'headingId' => 'process-title'])
@include('public.process')
</div>
</section>

@isset($cat['tours'])
@php $destinationImage = Html::image($v, 'tower-bridge', '', 'lazy', '100vw'); @endphp
<section class="destination-section {{ $destinationImage ? '' : 'is-text-only' }}" id="destinations" aria-labelledby="destination-title">
@if($destinationImage)<div class="destination-media">{!! $destinationImage !!}</div>@endif
<div class="container destination-inner">
<div class="destination-card">
<p class="eyebrow">{{ $u['places_kicker'] }}</p>
<h2 id="destination-title">{{ $u['places_title'] }}</h2>
<p>{{ $u['places_intro'] }}</p>
<a class="text-link" href="{{ $links['tours'] }}"><span>{{ $u['explore_places'] }}</span>{!! Html::arrow() !!}</a>
</div>
</div>
</section>
@endisset

@if($faqPage && !empty($faqPage['faq_items']))
<section class="section home-faq" aria-labelledby="home-faq-title">
<div class="container faq-layout">
<div class="faq-intro">
<p class="eyebrow">{{ $faqPage['eyebrow'] }}</p>
<h2 id="home-faq-title">{{ $faqPage['title'] }}</h2>
<p class="lede">{{ $faqPage['intro'] }}</p>
<a class="text-link" href="{{ $faqPage['url'] }}"><span>{{ $u['view_faqs'] }}</span>{!! Html::arrow() !!}</a>
</div>
@include('public.faq-list', ['faqItems' => array_slice($faqPage['faq_items'], 0, 4), 'faqHeading' => 'h3'])
</div>
</section>
@endif
