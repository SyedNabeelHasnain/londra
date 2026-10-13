<?php
$kind = $p['kind'];
$vehicle = null;
foreach ($v['vehicles'] as $candidate) { if ($candidate['page_key'] === $p['key']) { $vehicle = $candidate; break; } }
$serviceContext = $kind === 'service' ? $p['key'] : ($kind === 'airport' ? 'airport-transfers' : ($kind === 'tour' ? 'private-tours' : ''));
if ($serviceContext && !isset($cat[$serviceContext])) { $serviceContext = ''; }
$paragraphs = array_values(array_filter(preg_split('/\n\s*\n/u', (string) ($p['body'] ?? '')) ?: [], static fn($text) => trim($text) !== ''));
$isCollection = in_array($kind, ['services','fleet','airports','tours','faq','directory'], true);
$isPartnership = $p['key'] === 'partners';
$breadcrumbs = \App\Presentation\SiteViewModel::breadcrumbs($v);
?>
<nav class="breadcrumb wrap" aria-label="<?= $e($u['breadcrumb_label']) ?>">
<?php foreach($breadcrumbs as $index=>$crumb): ?>
<?php if($index): ?><span aria-hidden="true">/</span><?php endif; ?>
<?php if($crumb['url'] !== null): ?><a href="<?= $e($crumb['url']) ?>"><?= $e($crumb['title']) ?></a><?php else: ?><span aria-current="page"><?= $e($crumb['title']) ?></span><?php endif; ?>
<?php endforeach; ?>
</nav>
<?php if($kind === 'privacy-request'): ?>
<?php require __DIR__.'/privacy-request.php'; ?>
<?php elseif(in_array($kind, ['contact','enquiry'], true)): ?>
<section class="contact-section contact-layout section wrap">
<div class="contact-copy"><p class="eyebrow"><?= $e($p['eyebrow'] ?: $u['contact_kicker']) ?></p><h1><?= $e($p['title']) ?></h1><p class="lede"><?= $e($kind === 'enquiry' && !$v['accept_inquiries'] ? $u['offline_contact_intro'] : $p['intro']) ?></p>
<?php foreach($paragraphs as $paragraph): ?><p class="contact-detail"><?= $e($paragraph) ?></p><?php endforeach; ?>
</div>
<?php $renderContactMethods(); ?>
<div class="contact-paper"><p class="eyebrow"><?= $e($u['private_contact']) ?></p><h2><?= $e($u['form_title']) ?></h2>
<p><?= $e($v['accept_inquiries'] ? $u['form_intro'] : $u['offline_contact_intro']) ?></p><?php $renderForm($kind === 'contact' ? 'contact' : 'booking', 'page-form'); ?>
</div>
</section>
<?php elseif($kind === 'policy'): ?>
<section class="policy-section section wrap"><div class="policy-title"><p class="eyebrow">Londra Transfers</p><h1><?= $e($p['title']) ?></h1><p class="lede"><?= $e($p['intro']) ?></p></div><article class="policy-prose">
<?php foreach($paragraphs as $paragraph): $parts = explode("\n", $paragraph, 2); ?>
<?php if(count($parts) === 2 && preg_match('/^\d+\./u', $parts[0])): ?><section><h2><?= $e($parts[0]) ?></h2><p><?= $e($parts[1]) ?></p></section><?php else: ?><p><?= $e($paragraph) ?></p><?php endif; ?>
<?php endforeach; ?>
</article></section>
<?php else: ?>
<?php if($isCollection): ?>
<section class="page-intro wrap" aria-labelledby="page-title"><div><p class="eyebrow"><?= $e($p['eyebrow'] ?: $u['signature']) ?></p><h1 id="page-title"><?= $e($p['title']) ?></h1></div><p class="lede"><?= $e($p['intro']) ?></p></section>
<?php if($paragraphs): ?><div class="collection-body wrap"><?php foreach($paragraphs as $paragraph): ?><p class="collection-note"><?= $e($paragraph) ?></p><?php endforeach; ?></div><?php endif; ?>
<?php else: $heroImage = $photos($p['image_key'], '', true, '(min-width: 1480px) 690px, (min-width: 650px) 50vw, 100vw'); ?>
<section class="page-hero wrap <?= $heroImage ? '' : 'is-text-only' ?>"><div class="page-hero-copy"><p class="eyebrow"><?= $e($p['eyebrow'] ?: $u['signature']) ?></p><h1><?= $e($p['title']) ?></h1><p class="lede"><?= $e($p['intro']) ?></p><div class="hero-actions">
<?php if($vehicle): ?><span class="capacity"><?= $icon('person') ?><strong><?= (int) $vehicle['passengers'] ?></strong><span><?= $e($u['passengers']) ?></span></span><?php endif; ?>
<?php if($isPartnership): ?><a class="button" href="<?= $e($link('contact')) ?>"><?= $e($u['contact']) ?><?= $arrow() ?></a><?php else: ?><?= $cta($u['enquire'], 'button', $serviceContext) ?><?php endif; ?>
</div></div><?php if($heroImage): ?><figure class="page-hero-photo media-frame"><?= $heroImage ?></figure><?php endif; ?></section>
<?php endif; ?>
<?php if(in_array($kind, ['service','vehicle','airport','tour','editorial'], true)): ?>
<?php $luggage = $vehicle ? $luggageFacts($vehicle) : ''; $hasDetail = !empty($p['highlights']) || $paragraphs || !empty($p['detail_heading']) || $luggage; ?>
<section class="detail-section section wrap <?= $hasDetail ? '' : 'is-text-only' ?>" id="overview"><?php if($hasDetail): ?><article class="detail-prose">
<?php if($p['highlights'] || $luggage): ?><section class="detail-summary"><h2><?= $e($kind === 'tour' ? $u['destinations'] : $u['details']) ?></h2><?php if($p['highlights']): ?><ul class="detail-highlights"><?php foreach($p['highlights'] as $item): ?><li><?= $e($item) ?></li><?php endforeach; ?></ul><?php endif; ?><?= $luggage ?></section><?php endif; ?>
<?php if(!empty($p['detail_heading'])): ?><h2><?= $e($p['detail_heading']) ?></h2><?php endif; ?>
<?php foreach($paragraphs as $paragraph): ?><p><?= $e($paragraph) ?></p><?php endforeach; ?>
</article><?php endif; ?><aside class="journey-aside"><p class="eyebrow"><?= $e($u['contact_kicker']) ?></p><h2><?= $e($isPartnership ? $u['partner_contact_title'] : $u['detail_contact']) ?></h2><p><?= $e($isPartnership ? $u['partner_contact_intro'] : $u['planning_note']) ?></p>
<?php if($isPartnership): ?><a class="button" href="<?= $e($link('contact')) ?>"><?= $e($u['contact']) ?><?= $arrow() ?></a><?php else: ?><?= $cta($u['enquire'], 'button', $serviceContext) ?><?php endif; ?>
<div class="aside-contact"><a href="tel:<?= $e($v['contact']['phone_link']) ?>"><?= $e($v['contact']['phone_display']) ?></a><a href="mailto:<?= $e($v['contact']['email']) ?>"><?= $e($v['contact']['email']) ?></a></div></aside></section>
<?php if($vehicle): ?>
<section class="related-section section wrap"><?php $sectionTitle($u['fleet_kicker'], $u['related_vehicles']); ?><div class="related-grid">
<?php foreach($v['vehicles'] as $other): if($other['page_key'] === $vehicle['page_key'] || !isset($cat[$other['page_key']])) { continue; } $item = $cat[$other['page_key']]; $image = $photos($other['image_key'], '', false, '(min-width: 1480px) 420px, (min-width: 650px) 30vw, 100vw'); ?>
<article><?php if($image): ?><a href="<?= $e($item['url']) ?>" class="related-photo media-frame" tabindex="-1" aria-hidden="true"><?= $image ?></a><?php endif; ?><h3><a href="<?= $e($item['url']) ?>"><?= $e($item['title']) ?></a></h3><p><?= (int) $other['passengers'] ?> <?= $e($u['passengers']) ?></p><a class="text-link" href="<?= $e($item['url']) ?>"><?= $e($namedLabel('view_named_vehicle', str_replace('Mercedes-Benz ', '', $item['title']))) ?><?= $arrow() ?></a></article>
<?php endforeach; ?>
</div></section>
<?php elseif(in_array($kind, ['service','airport','tour'], true)): ?>
<section class="process-section section wrap"><?php $sectionTitle($u['contact_kicker'], $u['process_title']); ?><?php $renderProcess(); ?></section>
<?php endif; ?>
<?php elseif($kind === 'services'): ?>
<section class="service-index section wrap"><?php if($services): ?><nav class="service-shortcuts" aria-label="<?= $e($u['services']) ?>"><?php foreach($services as $item): ?><a href="#service-<?= $e($item['key']) ?>"><?= $e($item['title']) ?></a><?php endforeach; ?></nav><?php endif; ?><?php $renderServiceIndex($services); ?></section>
<?php elseif($kind === 'fleet'): ?>
<section class="fleet-section fleet-index section"><div class="wrap"><?php $renderFleet('fleet-page', 2); ?></div></section>
<?php elseif($kind === 'airports'): ?>
<section class="section wrap airport-index"><div class="airport-card-grid">
<?php foreach($airports as $item): ?><a href="<?= $e($item['url']) ?>"><span class="eyebrow"><?= $e($item['eyebrow']) ?></span><h2><?= $e($item['title']) ?></h2><p><?= $e($item['intro']) ?></p><span class="text-link"><?= $e($namedLabel('explore_named', $item['title'])) ?><?= $arrow() ?></span></a><?php endforeach; ?>
</div><?php if(isset($cat['private-aviation'])): ?><a class="text-link" href="<?= $e($link('private-aviation')) ?>"><?= $e($cat['private-aviation']['title']) ?><?= $arrow() ?></a><?php endif; ?></section>
<?php elseif($kind === 'tours'): ?>
<section class="service-index section wrap"><?php $renderServiceIndex($tours); ?></section>
<?php elseif($kind === 'faq'): ?>
<section class="faq-section section wrap"><div class="faq-list"><?php foreach($p['faq_items'] ?? [] as $item): ?><details><summary><h2><?= $e($item['q']) ?></h2><span class="faq-plus" aria-hidden="true"><?= $icon('plus') ?></span></summary><p><?= $e($item['a']) ?></p></details><?php endforeach; ?></div><aside class="faq-contact"><h2><?= $e($u['faq_help_title']) ?></h2><p><?= $e($u['faq_help_text']) ?></p><a href="<?= $e($link('contact')) ?>" class="text-link"><?= $e($u['contact']) ?><?= $arrow() ?></a></aside></section>
<?php elseif($kind === 'directory'): ?>
<section class="directory-section section wrap">
<?php foreach(['service'=>$u['services'],'airport'=>$u['airports'],'vehicle'=>$u['fleet'],'tour'=>$u['tours'],'editorial'=>$u['about']] as $group=>$label): ?><div><h2><?= $e($label) ?></h2><nav aria-label="<?= $e($label) ?>"><?php foreach($cat as $item): if($item['kind'] !== $group) { continue; } ?><a href="<?= $e($item['url']) ?>"><?= $e($item['title']) ?><?= $arrow() ?></a><?php endforeach; ?></nav></div><?php endforeach; ?>
<div><h2><?= $e($u['contact']) ?></h2><a href="<?= $e($link('contact')) ?>"><?= $e($u['contact']) ?><?= $arrow() ?></a><?php if(isset($cat['faqs'])): ?><a href="<?= $e($link('faqs')) ?>"><?= $e($u['faq']) ?><?= $arrow() ?></a><?php endif; ?></div>
</section>
<?php endif; ?>
<?php endif; ?>
