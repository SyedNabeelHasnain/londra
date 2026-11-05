@php
    $policyBlocks = [];
    foreach ($paragraphs as $paragraph) {
        $policyParts = explode("\n", $paragraph, 2);
        if (count($policyParts) === 2 && preg_match('/^(\d+)\./u', $policyParts[0], $policyNumber)) {
            $policyBlocks[] = ['id' => 'section-'.$policyNumber[1], 'heading' => $policyParts[0], 'text' => $policyParts[1]];
        } else {
            $policyBlocks[] = ['id' => null, 'heading' => null, 'text' => $paragraph];
        }
    }
    $policyContents = array_values(array_filter($policyBlocks, static fn(array $block) => $block['id'] !== null));
    $policyIds = array_column($policyContents, 'id');
    if (count($policyIds) !== count(array_unique($policyIds))) { $policyContents = []; }
@endphp
<section class="section policy-section" aria-labelledby="page-title">
<div class="container policy-container {{ count($policyContents) > 1 ? 'has-contents' : '' }}">
<header class="policy-header">
<p class="eyebrow">{{ $p['eyebrow'] ?: 'Londra Transfers' }}</p>
<h1 id="page-title">{{ $p['title'] }}</h1>
<p class="lede">{{ $p['intro'] }}</p>
</header>
<div class="policy-layout">
@if(count($policyContents) > 1)
<nav class="policy-contents" aria-labelledby="policy-contents-title" data-contents>
<h2 class="eyebrow policy-contents-title" id="policy-contents-title">{{ $u['on_this_page'] }}</h2>
<ul>
@foreach($policyContents as $entry)
<li><a href="#{{ $entry['id'] }}">{{ $entry['heading'] }}</a></li>
@endforeach
</ul>
</nav>
@endif
<article class="policy-prose">
@foreach($policyBlocks as $block)
@if($block['heading'] !== null)
<section @if(count($policyContents) > 1) id="{{ $block['id'] }}" @endif><h2>{{ $block['heading'] }}</h2><p>{{ $block['text'] }}</p></section>
@else
<p>{{ $block['text'] }}</p>
@endif
@endforeach
</article>
</div>
</div>
</section>
