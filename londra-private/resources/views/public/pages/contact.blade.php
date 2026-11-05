@use('App\Presentation\Html')
<section class="section contact-section" aria-labelledby="page-title">
<div class="container contact-grid">
<div class="contact-copy">
<p class="eyebrow">{{ $p['eyebrow'] ?: $u['contact_kicker'] }}</p>
<h1 id="page-title">{{ $p['title'] }}</h1>
<p class="lede">{{ $kind === 'enquiry' && !$v['accept_inquiries'] ? $u['offline_contact_intro'] : $p['intro'] }}</p>
@foreach($paragraphs as $paragraph)
<p class="contact-detail">{{ $paragraph }}</p>
@endforeach
@include('public.contact-methods')
</div>
<div class="form-card">
<p class="eyebrow">{{ $u['private_contact'] }}</p>
<h2>{{ $u['form_title'] }}</h2>
<p class="form-card-intro">{{ $v['accept_inquiries'] ? $u['form_intro'] : $u['offline_contact_intro'] }}</p>
@include('public.form', ['formType' => $kind === 'contact' ? 'contact' : 'booking', 'formId' => 'page-form'])
</div>
</div>
</section>
