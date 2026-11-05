@use('App\Presentation\Html')
@use('App\Presentation\SiteViewModel')
@php
    $footerServices = SiteViewModel::ofKind($v, 'service');
    $footerCompany = array_values(array_filter(['fleet', 'airports', 'tours', 'about', 'partners', 'faqs', 'contact', 'directory'], static fn(string $key) => isset($cat[$key])));
    $footerLegal = array_values(array_filter($cat, static fn(array $item) => in_array($item['kind'], ['policy', 'privacy-request'], true)));
@endphp
<footer class="site-footer" id="site-footer">
<div class="footer-skyline" aria-hidden="true"><img src="{{ Html::asset($v, 'assets/artwork/skyline-gold.webp') }}" width="1400" height="331" alt="" loading="lazy" decoding="async"></div>
<div class="container footer-main">
<div class="footer-identity">
@include('public.brand', ['brandClass' => 'footer-brand'])
<p class="footer-description">{{ $u['footer_description'] }}</p>
<ul class="footer-social">
<li><a class="icon-button" href="tel:{{ $contact['phone_link'] }}" aria-label="{{ $u['phone'] }}: {{ $contact['phone_display'] }}">{!! Html::icon('phone') !!}</a></li>
<li><a class="icon-button" href="mailto:{{ $contact['email'] }}" aria-label="{{ $u['email'] }}: {{ $contact['email'] }}">{!! Html::icon('mail') !!}</a></li>
<li><a class="icon-button" href="{{ $whatsapp }}" rel="noopener noreferrer" aria-label="WhatsApp">{!! Html::icon('chat') !!}</a></li>
<li><a class="icon-button" href="{{ $instagram }}" rel="noopener noreferrer" aria-label="Instagram">{!! Html::icon('instagram') !!}</a></li>
</ul>
</div>
@if($footerServices)
<nav class="footer-column" aria-labelledby="footer-services-title"><h2 class="footer-title" id="footer-services-title">{{ $u['footer_services'] }}</h2><ul>
@foreach($footerServices as $item)
<li><a href="{{ $item['url'] }}">{{ $item['title'] }}</a></li>
@endforeach
</ul></nav>
@endif
@if($footerCompany)
<nav class="footer-column" aria-labelledby="footer-company-title"><h2 class="footer-title" id="footer-company-title">{{ $u['footer_company'] }}</h2><ul>
@foreach($footerCompany as $key)
<li><a href="{{ $links[$key] }}">{{ $cat[$key]['title'] }}</a></li>
@endforeach
</ul></nav>
@endif
<div class="footer-column footer-contact">
<h2 class="footer-title">{{ $u['footer_connect'] }}</h2>
<ul>
<li><a href="tel:{{ $contact['phone_link'] }}"><span class="footer-contact-label">{{ $u['phone'] }}</span>{{ $contact['phone_display'] }}</a></li>
<li><a href="mailto:{{ $contact['email'] }}"><span class="footer-contact-label">{{ $u['email'] }}</span>{{ $contact['email'] }}</a></li>
<li><a href="{{ $whatsapp }}" rel="noopener noreferrer"><span class="footer-contact-label">{{ $u['contact_direct'] }}</span>WhatsApp</a></li>
</ul>
<a class="button button-small" href="{{ $enquiryUrl }}" data-open-enquiry data-service="" data-service-name=""><span>{{ $u['enquire_short'] }}</span>{!! Html::arrow() !!}</a>
</div>
</div>
<div class="container footer-legal">
<p class="footer-company">Londra Transfers Limited · {{ $u['company_number'] }}: 14856485<br>{{ $u['registered_office'] }}: Regus, Cowork Desk 130, 6-9 The Square, Stockley Park, Uxbridge, UB11 1FW, England</p>
@if($footerLegal)
<nav class="footer-policies" aria-label="{{ $u['navigation_policies'] }}"><ul>
@foreach($footerLegal as $item)
<li><a href="{{ $item['url'] }}">{{ $item['title'] }}</a></li>
@endforeach
</ul></nav>
@endif
</div>
<div class="container footer-bottom">
<p>© {{ date('Y') }} Londra Transfers Limited</p>
<a class="back-to-top" href="#top"><span>{{ $u['back_top'] }}</span>{!! Html::icon('up', 18) !!}</a>
</div>
</footer>
<nav class="mobile-actionbar" data-mobile-actionbar aria-label="{{ $u['direct_contact'] }}">
<a class="actionbar-call" href="tel:{{ $contact['phone_link'] }}">{!! Html::icon('phone', 18) !!}<span>{{ $u['phone_action'] }}</span></a>
<a class="actionbar-whatsapp" href="{{ $whatsapp }}" rel="noopener noreferrer">{!! Html::icon('chat', 18) !!}<span>WhatsApp</span></a>
<a class="button button-small actionbar-enquire" href="{{ $enquiryUrl }}" data-open-enquiry data-service="" data-service-name=""><span>{{ $u['enquire_short'] }}</span>{!! Html::arrow() !!}</a>
</nav>
