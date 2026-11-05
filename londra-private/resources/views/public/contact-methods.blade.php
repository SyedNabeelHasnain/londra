@use('App\Presentation\Html')
<ul class="contact-methods {{ $methodsClass ?? '' }}">
<li><a href="tel:{{ $contact['phone_link'] }}"><span class="contact-method-icon">{!! Html::icon('phone') !!}</span><span class="contact-method-text"><span class="contact-method-label">{{ $u['phone'] }}</span><strong>{{ $contact['phone_display'] }}</strong></span>{!! Html::arrow() !!}</a></li>
<li><a href="mailto:{{ $contact['email'] }}"><span class="contact-method-icon">{!! Html::icon('mail') !!}</span><span class="contact-method-text"><span class="contact-method-label">{{ $u['email'] }}</span><strong>{{ $contact['email'] }}</strong></span>{!! Html::arrow() !!}</a></li>
<li><a href="{{ $whatsapp }}" rel="noopener noreferrer"><span class="contact-method-icon">{!! Html::icon('chat') !!}</span><span class="contact-method-text"><span class="contact-method-label">{{ $u['contact_direct'] }}</span><strong>WhatsApp</strong></span>{!! Html::arrow() !!}</a></li>
</ul>
