@use('App\Presentation\Html')
@use('App\Presentation\SiteViewModel')
@php
    $languageNames = ['en' => 'English', 'it' => 'Italiano', 'es' => 'Español'];
    $headerServices = SiteViewModel::ofKind($v, 'service');
    $currentGroup = SiteViewModel::currentGroup($v);
    $headerState = static function (string $key) use ($p, $currentGroup): string {
        if ($p['key'] === $key) { return 'aria-current="page"'; }
        return (SiteViewModel::COLLECTIONS[$currentGroup] ?? null) === $key ? 'class="is-section"' : '';
    };
@endphp
<header class="site-header" data-site-header>
<div class="header-bar container">
@include('public.brand', ['brandClass' => 'header-brand'])
<nav class="primary-nav" aria-label="{{ $u['menu'] }}">
<ul class="primary-nav-list">
@foreach(SiteViewModel::HEADER as $key)
@continue(!isset($cat[$key]))
@if($key === 'services' && $headerServices)
<li class="primary-nav-item has-panel" data-nav-panel>
<a href="{{ $links[$key] }}" {!! $headerState($key) !!}>{{ $u[$key] }}</a>
<button class="nav-panel-toggle" type="button" aria-expanded="false" aria-controls="services-panel" data-nav-panel-toggle><span class="visually-hidden">{{ $u['show_services'] }}</span>{!! Html::icon('chevron', 16) !!}</button>
<div class="nav-panel" id="services-panel" data-nav-panel-content>
<div class="nav-panel-inner">
<div class="nav-panel-intro"><p class="eyebrow">{{ $u['services_kicker'] }}</p><p class="nav-panel-title">{{ $u['services_title'] }}</p><a class="text-link" href="{{ $links['services'] }}"><span>{{ $u['view_all_services'] }}</span>{!! Html::arrow() !!}</a></div>
<ul class="nav-panel-links">
@foreach($headerServices as $item)
<li><a href="{{ $item['url'] }}" @if($p['key'] === $item['key']) aria-current="page" @endif><span class="nav-panel-link-title">{{ $item['title'] }}</span><span class="nav-panel-link-intro">{{ $item['intro'] }}</span></a></li>
@endforeach
</ul>
</div>
</div>
</li>
@else
<li class="primary-nav-item"><a href="{{ $links[$key] }}" {!! $headerState($key) !!}>{{ $u[$key] }}</a></li>
@endif
@endforeach
</ul>
</nav>
<div class="header-actions">
<details class="language-switch" data-language-switch>
<summary><span class="visually-hidden">{{ $u['language'] }}</span> <span lang="{{ $v['locale'] }}">{{ strtoupper($v['locale']) }}</span>{!! Html::icon('chevron', 14) !!}</summary>
<div class="language-menu">
@foreach($languageNames as $code => $name)
@continue(!isset($v['language_choices'][$code]))
<a href="{{ $v['language_choices'][$code] }}" lang="{{ $code }}" hreflang="{{ $code }}" @if($v['locale'] === $code) aria-current="true" @endif><span>{{ $name }}</span><small>{{ strtoupper($code) }}</small></a>
@endforeach
</div>
</details>
<button class="icon-button theme-toggle" type="button" data-theme-toggle aria-pressed="false" aria-label="{{ $u['light'] }}"><span class="theme-icon-sun">{!! Html::icon('sun') !!}</span><span class="theme-icon-moon">{!! Html::icon('moon') !!}</span></button>
<a class="button button-small header-enquiry" href="{{ $enquiryUrl }}" data-open-enquiry data-service="" data-service-name=""><span>{{ $u['enquire_short'] }}</span>{!! Html::arrow() !!}</a>
<a class="icon-button menu-toggle" href="{{ $links['directory'] ?? '#site-footer' }}" data-open-navigation aria-label="{{ $u['open_navigation'] }}"><span class="menu-lines" aria-hidden="true"><i></i><i></i><i></i></span></a>
</div>
</div>
</header>
