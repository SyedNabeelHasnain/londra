<?php $heroImage = $photos($p['image_key'], '', true, '(min-width: 1480px) 740px, (min-width: 1000px) 58vw, 100vw'); $featuredServices = array_intersect_key($services, array_flip(['airport-transfers','private-transfers','chauffeur-service'])); ?>
<section class="home-hero wrap <?= $heroImage ? '' : 'is-text-only' ?>" aria-labelledby="home-title">
<div class="hero-copy"><p class="eyebrow"><?= $e($p['eyebrow']) ?></p><h1 id="home-title"><?= $e($p['title']) ?></h1><p class="hero-intro"><?= $e($p['intro']) ?></p>
<div class="hero-actions"><?= $cta($u['enquire'], 'button') ?><?php if(isset($cat['services'])): ?><a href="<?= $e($link('services')) ?>" class="text-link"><?= $e($u['all_services']) ?><?= $arrow() ?></a><?php endif; ?></div>
<?php foreach(array_filter(preg_split('/\n\s*\n/u', (string) ($p['body'] ?? '')) ?: []) as $paragraph): ?><p class="hero-detail"><?= $e($paragraph) ?></p><?php endforeach; ?>
<p class="hero-location"><?= $e($u['location']) ?></p></div>
<?php if($heroImage): ?><figure class="hero-photo media-frame"><?= $heroImage ?></figure><?php endif; ?>
</section>
<?php if($featuredServices): ?>
<section class="section wrap" id="journeys" aria-labelledby="services-title">
<?php $sectionTitle($u['services_kicker'], $u['services_title'], $u['services_intro'], 'services-title'); ?>
<div class="service-cards">
<?php foreach($featuredServices as $item): $image = $photos($item['image_key'], '', false, '(min-width: 1480px) 420px, (min-width: 1000px) 30vw, (min-width: 650px) 45vw, 100vw'); ?>
<article class="service-card <?= $image ? '' : 'is-text-only' ?>">
<?php if($image): ?><a class="card-photo media-frame" href="<?= $e($item['url']) ?>" tabindex="-1" aria-hidden="true"><?= $image ?></a><?php endif; ?>
<div class="card-copy"><h3><a href="<?= $e($item['url']) ?>"><?= $e($item['title']) ?></a></h3><p><?= $e($item['intro']) ?></p><a class="text-link" href="<?= $e($item['url']) ?>"><?= $e($namedLabel('explore_named', $item['title'])) ?><?= $arrow() ?></a></div>
</article>
<?php endforeach; ?>
</div>
<?php if(isset($cat['services'])): ?><div class="section-footer"><a class="text-link" href="<?= $e($link('services')) ?>"><?= $e($u['view_all_services']) ?><?= $arrow() ?></a></div><?php endif; ?>
</section>
<?php endif; ?>
<?php if($airports): $arrivalsImage = $photos('airport-transfer', '', false, '(min-width: 1480px) 640px, (min-width: 800px) 45vw, 100vw'); ?>
<section class="section wrap arrivals-section <?= $arrivalsImage ? '' : 'is-text-only' ?>" id="arrivals" aria-labelledby="airports-title">
<div class="arrivals-copy"><p class="eyebrow"><?= $e($u['l4_airports_label']) ?></p><h2 id="airports-title"><?= $e($u['airport_title']) ?></h2><p><?= $e($u['airport_intro']) ?></p><div class="airport-links">
<?php foreach($airports as $item): ?><a href="<?= $e($item['url']) ?>"><span><?= $e($item['title']) ?></span><?= $arrow() ?></a><?php endforeach; ?>
</div><?php if(isset($cat['airports'])): ?><a class="text-link" href="<?= $e($link('airports')) ?>"><?= $e($u['all_airports']) ?><?= $arrow() ?></a><?php endif; ?></div>
<?php if($arrivalsImage): ?><figure class="arrivals-photo media-frame"><?= $arrivalsImage ?></figure><?php endif; ?>
</section>
<?php endif; ?>
<?php if(array_filter($v['vehicles'], static fn($vehicle) => isset($cat[$vehicle['page_key']]))): ?>
<section class="fleet-section section" id="fleet" aria-labelledby="fleet-title"><div class="wrap"><?php $sectionTitle($u['fleet_kicker'], $u['fleet_title'], $u['fleet_intro'], 'fleet-title'); ?><?php $renderFleet('home-fleet'); ?></div></section>
<?php endif; ?>
<section class="process-section section wrap" aria-labelledby="process-title"><?php $sectionTitle($u['contact_kicker'], $u['process_title'], '', 'process-title'); ?><?php $renderProcess(); ?></section>
<?php $experienceImage = $photos('chauffeur-detail', '', false, '(min-width: 1480px) 640px, (min-width: 800px) 45vw, 100vw'); ?>
<section class="section wrap experience-section <?= $experienceImage ? '' : 'is-text-only' ?>" aria-labelledby="experience-title">
<div class="experience-copy"><p class="eyebrow"><?= $e($u['experience_kicker']) ?></p><h2 id="experience-title"><?= $e($u['experience_title']) ?></h2><p><?= $e($u['experience_body']) ?></p><ul class="principles"><li><span><?= $e($u['comfort_word']) ?></span><p><?= $e($u['value_comfort']) ?></p></li><li><span><?= $e($u['punctuality_word']) ?></span><p><?= $e($u['value_punctuality']) ?></p></li><li><span><?= $e($u['discretion_word']) ?></span><p><?= $e($u['value_discretion']) ?></p></li></ul><?php if(isset($cat['about'])): ?><a class="text-link" href="<?= $e($link('about')) ?>"><?= $e($u['experience_link']) ?><?= $arrow() ?></a><?php endif; ?></div>
<?php if($experienceImage): ?><figure class="experience-photo media-frame"><?= $experienceImage ?></figure><?php endif; ?>
</section>
<?php if(isset($cat['tours'])): $destinationImage = $photos('tower-bridge', '', false, '100vw'); ?>
<section class="destination-section" id="destinations" aria-labelledby="destination-title">
<?php if($destinationImage): ?><div class="destination-photo"><?= $destinationImage ?></div><div class="destination-shade" aria-hidden="true"></div><?php endif; ?>
<div class="destination-inner wrap"><div class="destination-copy"><p class="eyebrow"><?= $e($u['places_kicker']) ?></p><h2 id="destination-title"><?= $e($u['places_title']) ?></h2><p><?= $e($u['places_intro']) ?></p><a class="text-link" href="<?= $e($link('tours')) ?>"><?= $e($u['explore_places']) ?><?= $arrow() ?></a></div></div>
</section>
<?php endif; ?>
<?php if(isset($cat['faqs']) && !empty($cat['faqs']['faq_items'])): $faq = $cat['faqs']; ?>
<section class="home-faq section wrap" aria-labelledby="home-faq-title">
<?php $sectionTitle($faq['eyebrow'], $faq['title'], $faq['intro'], 'home-faq-title'); ?>
<div class="faq-list"><?php foreach(array_slice($faq['faq_items'], 0, 4) as $item): ?><details><summary><h3><?= $e($item['q']) ?></h3><span class="faq-plus" aria-hidden="true"><?= $icon('plus') ?></span></summary><p><?= $e($item['a']) ?></p></details><?php endforeach; ?></div>
<div class="section-footer"><a class="text-link" href="<?= $e($faq['url']) ?>"><?= $e($u['view_faqs']) ?><?= $arrow() ?></a></div>
</section>
<?php endif; ?>
