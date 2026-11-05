@use('App\Presentation\Html')
@php $privacyAvailable = !empty($v['privacy_requests_allowed']); @endphp
<section class="section contact-section" aria-labelledby="page-title">
<div class="container contact-grid">
<div class="contact-copy">
<p class="eyebrow">{{ $p['eyebrow'] }}</p>
<h1 id="page-title">{{ $p['title'] }}</h1>
<p class="lede">{{ $p['intro'] }}</p>
@foreach($paragraphs as $paragraph)
<p class="contact-detail">{{ $paragraph }}</p>
@endforeach
<a class="text-link" href="mailto:{{ $contact['email'] }}"><span>{{ $contact['email'] }}</span>{!! Html::arrow() !!}</a>
</div>
<div class="form-card">
<h2>{{ $u['privacy_form_title'] }}</h2>
@if(!empty($v['privacy_receipt']))<p class="form-status" role="status">{{ $v['privacy_receipt'] }}</p>@endif
<form class="enquiry-form" data-privacy-form data-privacy-available="{{ $privacyAvailable ? 'true' : 'false' }}" method="post" action="/{{ $v['locale'] }}/privacy-requests" @unless($privacyAvailable) aria-describedby="privacy-availability" @endunless>
<input type="hidden" name="_token" value="{{ $v['csrf'] }}">
<input type="hidden" name="submission_id" value="{{ $v['submission_id'] }}">
<input type="hidden" name="form_token" value="{{ $v['privacy_form_token'] ?? '' }}">
<div class="honeypot" aria-hidden="true"><label for="privacy-website">Website</label><input id="privacy-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
@unless($privacyAvailable)
<p class="form-availability" id="privacy-availability" role="status">{{ $u['forms_unavailable'] }}</p>
@endunless
<fieldset class="form-controls" @unless($privacyAvailable) disabled aria-describedby="privacy-availability" @endunless>
<legend class="visually-hidden">{{ $u['privacy_form_title'] }}</legend>
<div class="form-field">
<label for="privacy-email">{{ $u['email'] }}</label>
<input id="privacy-email" name="email" type="email" autocomplete="email" maxlength="254" required value="{{ $v['old']['email'] ?? '' }}" @if(!empty($v['errors']['email'])) aria-invalid="true" @endif aria-describedby="privacy-email-error{{ $privacyAvailable ? ' privacy-email-hint' : '' }}">
<span class="field-error" id="privacy-email-error">{{ $v['errors']['email'][0] ?? '' }}</span>
</div>
@if($privacyAvailable)<p class="form-note" id="privacy-email-hint">{{ $u['privacy_email_hint'] }}</p>@endif
<button class="button form-submit" type="submit" @unless($privacyAvailable) disabled @endunless><span>{{ $u['privacy_submit'] }}</span>{!! Html::arrow() !!}</button>
</fieldset>
@isset($links['privacy'])<a class="text-link" href="{{ $links['privacy'] }}"><span>{{ $u['privacy_notice'] }}</span>{!! Html::arrow() !!}</a>@endisset
@foreach($v['errors'] as $errorField => $errorMessages)
@continue($errorField === 'email')
<p class="form-status" role="alert">{{ $errorMessages[0] ?? '' }}</p>
@endforeach
</form>
@unless($privacyAvailable)
<div class="privacy-direct">
<p>{{ $u['privacy_email_alternative'] }}</p>
<a class="text-link" href="mailto:{{ $contact['email'] }}"><span>{{ $contact['email'] }}</span>{!! Html::arrow() !!}</a>
<a class="text-link" href="tel:{{ $contact['phone_link'] }}"><span>{{ $contact['phone_display'] }}</span>{!! Html::arrow() !!}</a>
</div>
@endunless
</div>
</div>
</section>
