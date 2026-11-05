<section class="section policy-section" aria-labelledby="page-title">
<div class="container policy-container">
<header class="policy-header">
<p class="eyebrow">Londra Transfers</p>
<h1 id="page-title">{{ $p['title'] }}</h1>
<p class="lede">{{ $p['intro'] }}</p>
</header>
<article class="policy-prose">
@foreach($paragraphs as $paragraph)
@php $policyParts = explode("\n", $paragraph, 2); @endphp
@if(count($policyParts) === 2 && preg_match('/^\d+\./u', $policyParts[0]))
<section><h2>{{ $policyParts[0] }}</h2><p>{{ $policyParts[1] }}</p></section>
@else
<p>{{ $paragraph }}</p>
@endif
@endforeach
</article>
</div>
</section>
