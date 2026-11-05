@use('App\Presentation\Html')
@use('App\Presentation\SiteViewModel')
@php $deckVehicles = SiteViewModel::fleet($v); @endphp
@if($deckVehicles)
<div class="fleet-deck" data-deck>
<div class="fleet-toolbar">
@if($fleetNotes ?? true)<p class="fleet-notes">{{ $u['fleet_notes'] }}</p>@else<span></span>@endif
<button class="text-link compare-trigger" type="button" data-open-compare><span>{{ $u['fleet_compare'] }}</span>{!! Html::icon('plus', 18) !!}</button>
</div>
<div class="fleet-tabs" data-tablist data-label="{{ $u['fleet_selection'] }}">
@foreach($deckVehicles as $index => $vehicle)
@php $tabItem = $cat[$vehicle['page_key']]; @endphp
<button class="fleet-tab" type="button" data-tab="{{ $index }}" id="{{ $fleetId }}-tab-{{ $index }}" aria-controls="{{ $fleetId }}-panel-{{ $index }}"><span class="fleet-tab-name">{{ SiteViewModel::vehicleName($tabItem['title']) }}</span><span class="fleet-tab-meta">{{ $u['up_to'] }} {{ (int) $vehicle['passengers'] }} {{ $u['passengers'] }}</span></button>
@endforeach
</div>
@foreach($deckVehicles as $index => $vehicle)
@php $deckItem = $cat[$vehicle['page_key']]; $deckName = SiteViewModel::vehicleName($deckItem['title']); $deckImage = Html::image($v, $vehicle['image_key'], '', 'lazy', '(min-width: 1200px) 760px, (min-width: 900px) 58vw, 100vw'); @endphp
<div class="fleet-panel {{ $deckImage ? '' : 'is-text-only' }}" id="{{ $fleetId }}-panel-{{ $index }}" data-panel="{{ $index }}" aria-labelledby="{{ $fleetId }}-tab-{{ $index }}">
<div class="fleet-copy">
<p class="eyebrow">{{ $u['fleet_maker'] }}</p>
<{{ $fleetHeading }} class="fleet-name">{{ $deckName }}</{{ $fleetHeading }}>
<p class="capacity">{!! Html::icon('person', 18) !!}<span>{{ $u['up_to'] }} <strong>{{ (int) $vehicle['passengers'] }}</strong> {{ $u['passengers'] }}</span></p>
<p class="fleet-intro">{{ $deckItem['intro'] }}</p>
@include('public.luggage', ['luggageVehicle' => $vehicle])
@if($deckItem['highlights'])
<ul class="fleet-facts">
@foreach(array_slice($deckItem['highlights'], 0, 4) as $highlight)
<li>{{ $highlight }}</li>
@endforeach
</ul>
@endif
<a class="text-link" href="{{ $deckItem['url'] }}"><span>{{ $u['discover_more'] }}<span class="visually-hidden">: {{ $deckItem['title'] }}</span></span>{!! Html::arrow() !!}</a>
</div>
@if($deckImage)
<div class="fleet-visual">
<figure class="fleet-photo media">{!! $deckImage !!}</figure>
<button class="photo-open icon-button" type="button" data-open-gallery data-vehicle-name="{{ $deckItem['title'] }}" aria-label="{{ $u['fleet_image'] }}: {{ $deckItem['title'] }}">{!! Html::icon('expand', 18) !!}</button>
</div>
@endif
</div>
@endforeach
<div class="fleet-controls">
<p class="fleet-browse">{{ $u['fleet_browse'] }}</p>
<div class="fleet-arrows"><button class="circle-button" type="button" data-deck-prev aria-label="{{ $u['previous_vehicle'] }}">{!! Html::icon('back', 18) !!}</button><button class="circle-button" type="button" data-deck-next aria-label="{{ $u['next_vehicle'] }}">{!! Html::icon('arrow', 18) !!}</button></div>
</div>
</div>
@endif
