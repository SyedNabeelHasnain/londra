@use('App\Presentation\SiteViewModel')
@use('App\Presentation\Html')
@php $navigationGroups = SiteViewModel::navigation($v); $navigationCurrent = SiteViewModel::currentGroup($v); @endphp
<nav class="site-navigation" aria-label="{{ $u['menu_explore'] }}">
@isset($cat['home'])
<a class="nav-home" href="{{ $cat['home']['url'] }}" @if($p['key'] === 'home') aria-current="page" @endif>{{ $u['home'] }}</a>
@endisset
@foreach($navigationGroups as $group)
<details class="nav-group" data-navigation-group="{{ $group['key'] }}" @if($navigationCurrent === $group['key'] || ($p['kind'] === 'home' && $group['key'] === 'service')) open @endif>
<summary><span>{{ $group['label'] }}</span>{!! Html::icon('chevron', 16) !!}</summary>
<ul class="nav-group-links">
@if($group['overview'])
<li><a class="nav-overview" href="{{ $group['overview']['url'] }}" @if($p['key'] === $group['overview']['key']) aria-current="page" @endif>{{ $group['overview']['title'] }}</a></li>
@endif
@foreach($group['items'] as $item)
<li><a href="{{ $item['url'] }}" @if($p['key'] === $item['key']) aria-current="page" @endif>{{ $item['title'] }}</a></li>
@endforeach
</ul>
</details>
@endforeach
</nav>
