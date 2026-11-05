@use('App\Presentation\Html')
@php $invitationPartner = $p['key'] === 'partners'; $invitationImage = Html::image($v, 'private-transfer', '', 'lazy', '(min-width: 1200px) 560px, (min-width: 900px) 42vw, 100vw'); @endphp
<section class="invitation {{ $invitationImage ? '' : 'is-text-only' }}" aria-labelledby="invitation-title">
<div class="container invitation-grid">
<div class="invitation-copy">
<p class="eyebrow">{{ $u['contact_kicker'] }}</p>
<h2 id="invitation-title">@if($invitationPartner){{ $u['partner_contact_title'] }}@else{{ $u['invitation_first'] }} <em>{{ $u['invitation_second'] }}</em>@endif</h2>
<p class="lede">{{ $invitationPartner ? $u['partner_contact_intro'] : $u['cta_body'] }}</p>
<div class="actions">
@if($invitationPartner && isset($links['contact']))
<a class="button" href="{{ $links['contact'] }}"><span>{{ $u['contact'] }}</span>{!! Html::arrow() !!}</a>
@else
<a class="button" href="{{ $enquiryUrl }}" data-open-enquiry data-service="" data-service-name=""><span>{{ $u['enquire'] }}</span>{!! Html::arrow() !!}</a>
@endif
<a class="text-link" href="tel:{{ $contact['phone_link'] }}"><span>{{ $contact['phone_display'] }}</span>{!! Html::arrow() !!}</a>
</div>
</div>
@if($invitationImage)<figure class="invitation-media media">{!! $invitationImage !!}</figure>@endif
</div>
</section>
