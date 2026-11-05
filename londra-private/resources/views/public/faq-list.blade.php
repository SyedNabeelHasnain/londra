@use('App\Presentation\Html')
<div class="faq-list">
@foreach($faqItems as $faqItem)
<details class="faq-item">
<summary><{{ $faqHeading }} class="faq-question">{{ $faqItem['q'] }}</{{ $faqHeading }}><span class="faq-toggle" aria-hidden="true">{!! Html::icon('plus', 18) !!}</span></summary>
<p class="faq-answer">{{ $faqItem['a'] }}</p>
</details>
@endforeach
</div>
