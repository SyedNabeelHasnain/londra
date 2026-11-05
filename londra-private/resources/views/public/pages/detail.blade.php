@use('App\Presentation\Html')
@use('App\Presentation\SiteViewModel')
@php
    $detailImage = Html::image($v, $p['image_key'], '', 'eager-high', '(min-width: 1200px) 660px, (min-width: 900px) 52vw, 100vw');
    $detailHasContent = !empty($p['highlights']) || $paragraphs || !empty($p['detail_heading']) || ($pageVehicle && !empty($pageVehicle['luggage_approved']));
@endphp
<section class="page-hero {{ $detailImage ? '' : 'is-text-only' }}" aria-labelledby="page-title">
<div class="container page-hero-grid">
<div class="page-hero-copy">
<p class="eyebrow">{{ $p['eyebrow'] ?: $u['signature'] }}</p>
<h1 id="page-title">{{ $p['title'] }}</h1>
<p class="lede">{{ $p['intro'] }}</p>
<div class="actions">
@if($pageVehicle)
<p class="capacity">{!! Html::icon('person', 18) !!}<span>{{ $u['up_to'] }} <strong>{{ (int) $pageVehicle['passengers'] }}</strong> {{ $u['passengers'] }}</span></p>
@endif
@if($isPartnership && isset($links['contact']))
<a class="button" href="{{ $links['contact'] }}"><span>{{ $u['contact'] }}</span>{!! Html::arrow() !!}</a>
@else
<a class="button" href="{{ $enquiryUrl }}" data-open-enquiry data-service="{{ $serviceContext }}" data-service-name="{{ $serviceContextName }}"><span>{{ $u['enquire'] }}</span>{!! Html::arrow() !!}</a>
@endif
</div>
</div>
@if($detailImage)<figure class="page-hero-photo media">{!! $detailImage !!}</figure>@endif
</div>
</section>
<section class="section detail-section" id="overview">
<div class="container detail-grid {{ $detailHasContent ? '' : 'is-text-only' }}">
@if($detailHasContent)
<article class="detail-prose">
@if(!empty($p['highlights']) || ($pageVehicle && !empty($pageVehicle['luggage_approved'])))
<div class="detail-summary">
<h2 class="detail-summary-title">{{ $kind === 'tour' ? $u['destinations'] : $u['details'] }}</h2>
@if(!empty($p['highlights']))
<ul class="detail-highlights">
@foreach($p['highlights'] as $highlight)
<li>{{ $highlight }}</li>
@endforeach
</ul>
@endif
@if($pageVehicle)
@include('public.luggage', ['luggageVehicle' => $pageVehicle])
@endif
</div>
@endif
@if(!empty($p['detail_heading']))<h2>{{ $p['detail_heading'] }}</h2>@endif
@foreach($paragraphs as $paragraph)
<p>{{ $paragraph }}</p>
@endforeach
</article>
@endif
<aside class="journey-card" aria-labelledby="journey-card-title">
<p class="eyebrow">{{ $u['contact_kicker'] }}</p>
<h2 id="journey-card-title">{{ $isPartnership ? $u['partner_contact_title'] : $u['detail_contact'] }}</h2>
<p>{{ $isPartnership ? $u['partner_contact_intro'] : $u['planning_note'] }}</p>
@if($isPartnership && isset($links['contact']))
<a class="button" href="{{ $links['contact'] }}"><span>{{ $u['contact'] }}</span>{!! Html::arrow() !!}</a>
@else
<a class="button" href="{{ $enquiryUrl }}" data-open-enquiry data-service="{{ $serviceContext }}" data-service-name="{{ $serviceContextName }}"><span>{{ $u['enquire'] }}</span>{!! Html::arrow() !!}</a>
@endif
<ul class="journey-card-contact">
<li><a href="tel:{{ $contact['phone_link'] }}">{!! Html::icon('phone', 16) !!}<span>{{ $contact['phone_display'] }}</span></a></li>
<li><a href="mailto:{{ $contact['email'] }}">{!! Html::icon('mail', 16) !!}<span>{{ $contact['email'] }}</span></a></li>
<li><a href="{{ $whatsapp }}" rel="noopener noreferrer">{!! Html::icon('chat', 16) !!}<span>WhatsApp</span></a></li>
</ul>
</aside>
</div>
</section>
@if($pageVehicle)
@php $related = array_values(array_filter(SiteViewModel::fleet($v), static fn(array $other) => $other['page_key'] !== $pageVehicle['page_key'])); @endphp
@if($related)
<section class="section related-section" aria-labelledby="related-title">
<div class="container">
@include('public.section-heading', ['headingKicker' => $u['fleet_kicker'], 'headingTitle' => $u['related_vehicles'], 'headingIntro' => '', 'headingId' => 'related-title'])
<div class="card-grid">
@foreach($related as $other)
@php $relatedItem = $cat[$other['page_key']]; $relatedImage = Html::image($v, $other['image_key'], '', 'lazy', '(min-width: 1200px) 410px, (min-width: 720px) 45vw, 100vw'); @endphp
<article class="service-card {{ $relatedImage ? '' : 'is-text-only' }}">
@if($relatedImage)<a class="service-card-media media" href="{{ $relatedItem['url'] }}" tabindex="-1" aria-hidden="true">{!! $relatedImage !!}</a>@endif
<div class="service-card-body">
<h3><a href="{{ $relatedItem['url'] }}">{{ $relatedItem['title'] }}</a></h3>
<p class="capacity">{!! Html::icon('person', 18) !!}<span>{{ $u['up_to'] }} <strong>{{ (int) $other['passengers'] }}</strong> {{ $u['passengers'] }}</span></p>
<a class="text-link" href="{{ $relatedItem['url'] }}"><span>{{ $u['discover_more'] }}<span class="visually-hidden">: {{ $relatedItem['title'] }}</span></span>{!! Html::arrow() !!}</a>
</div>
</article>
@endforeach
</div>
</div>
</section>
@endif
@elseif(in_array($kind, ['service', 'airport', 'tour'], true))
<section class="section process-section" aria-labelledby="process-title">
<div class="container">
@include('public.section-heading', ['headingKicker' => $u['process_kicker'], 'headingTitle' => $u['process_title'], 'headingIntro' => '', 'headingId' => 'process-title'])
@include('public.process')
</div>
</section>
@endif
