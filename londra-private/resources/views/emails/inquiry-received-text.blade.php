LONDRA TRANSFERS

@if ($kind === 'customer')
{!! __('ui.email_greeting', [], $locale) !!}

{!! __('ui.email_body', [], $locale) !!}

{!! __('ui.reference', [], $locale) !!}: {!! $inquiry->reference !!}

{!! __('ui.email_disclaimer', [], $locale) !!}
@else
New {{ $inquiry->type === 'contact' ? 'contact' : 'journey' }} enquiry

Reference: {!! $inquiry->reference !!}
Language: {!! strtoupper($inquiry->locale) !!}

View in the protected enquiry desk:
{!! url('/admin/inquiries/'.$inquiry->id.'/edit') !!}

Client details are available in the protected administration area.
@endif

{!! __('ui.phone', [], $locale) !!} / WhatsApp: {!! $contact['phone_display'] !!}
{!! __('ui.email', [], $locale) !!}: {!! $contact['email'] !!}
Londra Transfers · {!! __('ui.location', [], $locale) !!}
