@use('App\Presentation\Html')
@php
    $formAvailable = !empty($v['accept_inquiries']);
    $formSubmission = $v['offline'] ? $v['submission_id'] : (string) \Illuminate\Support\Str::uuid();
    $formFields = [
        'name' => ['type' => 'text', 'autocomplete' => 'name', 'maxlength' => 120],
        'email' => ['type' => 'email', 'autocomplete' => 'email', 'maxlength' => 254],
        'phone' => ['type' => 'tel', 'autocomplete' => 'tel', 'maxlength' => 40],
    ];
    $formStatus = '';
    if ($v['receipt']) {
        $formStatus = $u['received'].' '.$u['reference'].': '.($v['receipt']['reference'] ?? '');
    } else {
        foreach ($v['errors'] as $errorField => $errorMessages) {
            if (in_array($errorField, ['name', 'email', 'phone'], true)) { continue; }
            $formStatus = trim($formStatus.' '.($errorMessages[0] ?? ''));
        }
    }
@endphp
<form class="enquiry-form" action="{{ $v['form_action'] }}" method="post" data-enquiry-form data-inquiries-available="{{ $formAvailable ? 'true' : 'false' }}" data-token-url="/{{ $v['locale'] }}/form-tokens" @unless($formAvailable) aria-describedby="{{ $formId }}-availability" @endunless>
<input type="hidden" name="_token" value="{{ $v['csrf'] }}">
<input type="hidden" name="submission_id" value="{{ $formSubmission }}">
<input type="hidden" name="form_token" value="{{ $v['form_token'] }}">
<input type="hidden" name="type" value="{{ $formType }}">
<input type="hidden" name="page_key" value="{{ $p['key'] }}">
@if($formType === 'booking')
<input type="hidden" name="service_key" value="{{ $p['kind'] === 'service' ? $p['key'] : '' }}">
@endif
<div class="honeypot" aria-hidden="true"><label for="{{ $formId }}-website">Website</label><input id="{{ $formId }}-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
@unless($formAvailable)
<p class="form-availability" id="{{ $formId }}-availability" role="status">{{ $u['forms_unavailable'] }}</p>
@endunless
<fieldset class="form-controls" @unless($formAvailable) disabled aria-describedby="{{ $formId }}-availability" @endunless>
<legend class="visually-hidden">{{ $u['form_title'] }}</legend>
@foreach($formFields as $fieldName => $field)
@php $fieldId = $formId.'-'.$fieldName; $fieldError = $v['errors'][$fieldName][0] ?? ''; @endphp
<div class="form-field">
<label for="{{ $fieldId }}">{{ $u[$fieldName] }}</label>
<input id="{{ $fieldId }}" name="{{ $fieldName }}" type="{{ $field['type'] }}" value="{{ $v['old'][$fieldName] ?? '' }}" autocomplete="{{ $field['autocomplete'] }}" required maxlength="{{ $field['maxlength'] }}" @if($fieldError !== '') aria-invalid="true" @endif aria-describedby="{{ $fieldId }}-error">
<span class="field-error" id="{{ $fieldId }}-error" data-field-error="{{ $fieldName }}">{{ $fieldError }}</span>
</div>
@endforeach
@if($formAvailable)
<p class="form-note">{{ $u['form_note'] }}</p>
@endif
@isset($links['privacy'])
<p class="form-note"><a href="{{ $links['privacy'] }}">{{ $u['privacy_notice'] }}</a></p>
@endisset
<button class="button form-submit" type="submit" @unless($formAvailable) disabled @endunless><span>{{ $u['send'] }}</span>{!! Html::arrow() !!}</button>
</fieldset>
<p class="form-status" data-form-status role="status" aria-live="polite">{{ $formStatus }}</p>
<a class="text-link form-reload" data-form-reload href="{{ $p['url'] }}" hidden><span>{{ $u['reload_form'] }}</span>{!! Html::arrow() !!}</a>
</form>
