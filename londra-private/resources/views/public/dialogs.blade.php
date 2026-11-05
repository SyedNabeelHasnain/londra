@use('App\Presentation\Html')
@use('App\Presentation\SiteViewModel')
@php
    $dialogFleet = SiteViewModel::fleet($v);
    $dialogLanguages = ['en' => 'English', 'it' => 'Italiano', 'es' => 'Español'];
@endphp
<dialog class="drawer drawer-left" id="navigation-dialog" aria-labelledby="navigation-title" closedby="any">
<div class="drawer-head">
@include('public.brand', ['brandClass' => 'drawer-brand'])
<button class="icon-button" type="button" data-close-dialog aria-label="{{ $u['close_navigation'] }}">{!! Html::icon('close') !!}</button>
</div>
<div class="drawer-body">
<h2 class="eyebrow drawer-title" id="navigation-title" tabindex="-1">{{ $u['menu_explore'] }}</h2>
@include('public.navigation')
<div class="drawer-section">
<p class="drawer-label">{{ $u['direct_contact'] }}</p>
@include('public.contact-methods', ['methodsClass' => 'contact-methods-compact'])
</div>
<nav class="drawer-languages" aria-label="{{ $u['language'] }}">
@foreach($dialogLanguages as $code => $name)
@continue(!isset($v['language_choices'][$code]))
<a href="{{ $v['language_choices'][$code] }}" lang="{{ $code }}" hreflang="{{ $code }}" @if($v['locale'] === $code) aria-current="true" @endif>{{ $name }}</a>
@endforeach
</nav>
</div>
</dialog>
<dialog class="drawer drawer-right enquiry-dialog" id="enquiry-dialog" aria-labelledby="enquiry-dialog-title" closedby="any">
<div class="drawer-head">
<p class="eyebrow">{{ $u['private_contact'] }}</p>
<button class="icon-button" type="button" data-close-dialog aria-label="{{ $u['close'] }}">{!! Html::icon('close') !!}</button>
</div>
<div class="drawer-body">
<h2 class="drawer-heading" id="enquiry-dialog-title" tabindex="-1">{{ $u['form_title'] }}</h2>
<p class="drawer-intro">{{ $v['accept_inquiries'] ? $u['form_intro'] : $u['offline_contact_intro'] }}</p>
<p class="enquiry-context" data-enquiry-context hidden><span>{{ $u['form_context'] }}:</span> <strong></strong></p>
@include('public.form', ['formType' => 'booking', 'formId' => 'modal-form'])
<div class="drawer-section">
<p class="drawer-label">{{ $u['direct_contact'] }}</p>
@include('public.contact-methods', ['methodsClass' => 'contact-methods-compact'])
</div>
</div>
</dialog>
@if($dialogFleet && in_array($p['kind'], ['home', 'fleet'], true))
<dialog class="modal compare-dialog" id="compare-dialog" aria-labelledby="compare-title" closedby="any">
<div class="modal-head">
<div><p class="eyebrow">{{ $u['fleet_kicker'] }}</p><h2 id="compare-title" tabindex="-1">{{ $u['fleet_compare_label'] }}</h2></div>
<button class="icon-button" type="button" data-close-dialog aria-label="{{ $u['fleet_compare_close'] }}">{!! Html::icon('close') !!}</button>
</div>
<div class="comparison-grid">
@foreach($dialogFleet as $vehicle)
@php $item = $cat[$vehicle['page_key']]; $image = Html::image($v, $vehicle['image_key'], '', 'hidden', '(min-width: 1200px) 270px, (min-width: 720px) 45vw, 90vw'); $name = SiteViewModel::vehicleName($item['title']); @endphp
<article class="comparison-card">
@if($image)<figure class="comparison-photo media">{!! $image !!}</figure>@endif
<p class="eyebrow">{{ $u['fleet_maker'] }}</p>
<h3>{{ $name }}</h3>
<p class="capacity">{!! Html::icon('person', 18) !!}<span><strong>{{ (int) $vehicle['passengers'] }}</strong> {{ $u['passengers'] }}</span></p>
<p class="comparison-intro">{{ $item['intro'] }}</p>
@include('public.luggage', ['luggageVehicle' => $vehicle])
@if($item['highlights'])
<ul class="comparison-facts">
@foreach(array_slice($item['highlights'], 0, 4) as $highlight)
<li>{{ $highlight }}</li>
@endforeach
</ul>
@endif
<a class="text-link" href="{{ $item['url'] }}"><span>{{ $u['discover_more'] }}<span class="visually-hidden">: {{ $item['title'] }}</span></span>{!! Html::arrow() !!}</a>
</article>
@endforeach
</div>
</dialog>
<dialog class="modal gallery-dialog" id="gallery-dialog" aria-labelledby="gallery-title" closedby="any">
<div class="modal-head">
<h2 class="gallery-title" id="gallery-title" tabindex="-1">{{ $u['gallery_title'] }}</h2>
<button class="icon-button" type="button" data-close-dialog aria-label="{{ $u['gallery_close'] }}">{!! Html::icon('close') !!}</button>
</div>
<div class="gallery-photo" data-gallery-photo></div>
</dialog>
@endif
