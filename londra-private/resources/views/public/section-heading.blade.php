<div class="section-heading">
<div><p class="eyebrow">{{ $headingKicker }}</p><h2 @if(!empty($headingId)) id="{{ $headingId }}" @endif>{{ $headingTitle }}</h2></div>
@if(!empty($headingIntro))<p class="section-intro">{{ $headingIntro }}</p>@endif
</div>
