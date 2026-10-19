<?php
use App\Presentation\Html;
$e = static fn($value) => Html::e($value);
$u = $v['u']; $p = $v['page']; $links = $v['links']; $cat = $v['catalog']; $base = $v['asset_base'];
$link = static function(string $key) use($links): string {
    if (!isset($links[$key])) { throw new \LogicException('The requested navigation page is not published.'); }
    return $links[$key];
};
$services = array_filter($cat, static fn($item) => $item['kind'] === 'service');
$airports = array_filter($cat, static fn($item) => $item['kind'] === 'airport');
$tours = array_filter($cat, static fn($item) => $item['kind'] === 'tour');
if (!empty($p['image_alt']) && !empty($p['image_key']) && isset($v['media'][$p['image_key']])) { $v['media'][$p['image_key']]['alt'][$v['locale']] = $p['image_alt']; }
$photos = static fn(?string $key, string $class = '', bool $priority = false, ?string $sizes = null) => Html::image($v, $key, $class, $priority, $sizes);
$arrow = static fn() => Html::arrow();
$namedLabel = static fn(string $key, string $title) => str_replace(':title', $title, $u[$key]);
$luggageFacts = static function(array $vehicle) use($u, $e): string {
    if (empty($vehicle['luggage_approved'])) { return ''; }
    $items = [];
    foreach(['large_luggage'=>'large_cases', 'cabin_luggage'=>'cabin_bags'] as $field=>$label) {
        $count = $vehicle[$field] ?? null;
        $number = $count === null ? false : filter_var($count, FILTER_VALIDATE_INT);
        if ($number === false || $number < 0) { continue; }
        $items[] = '<li><span>'.$e($u[$label]).'</span><strong>'.$number.'</strong></li>';
    }
    return $items ? '<ul class="approved-luggage">'.implode('', $items).'</ul>' : '';
};
$cta = static function(string $label, string $style = 'button', string $service = '') use($v, $e, $links, $cat): string {
    $destination = $links['enquiry'] ?? $links['contact'] ?? 'tel:'.$v['contact']['phone_link'];
    return '<a class="'.$e($style).'" href="'.$e($destination).'" data-open-enquiry data-service="'.$e($service).'" data-service-name="'.$e($cat[$service]['title'] ?? '').'"><span>'.$e($label).'</span>'.Html::arrow().'</a>';
};
$icon = static function(string $name): string {
    $paths = [
        'close'=>'<path d="m6 6 12 12M18 6 6 18"/>',
        'sun'=>'<circle cx="12" cy="12" r="4"/><path d="M12 1v2m0 18v2M1 12h2m18 0h2M4.2 4.2l1.4 1.4m12.8 12.8 1.4 1.4m0-15.6-1.4 1.4M5.6 18.4l-1.4 1.4"/>',
        'moon'=>'<path d="M20.8 13A9 9 0 0 1 11 3.2 9 9 0 1 0 20.8 13Z"/>',
        'plus'=>'<path d="M12 5v14M5 12h14"/>',
        'person'=>'<circle cx="12" cy="7" r="3"/><path d="M5 21v-3a7 7 0 0 1 14 0v3"/>',
        'chevron'=>'<path d="m7 10 5 5 5-5"/>',
        'back'=>'<path d="M20 12H4m6-6-6 6 6 6"/>',
        'up'=>'<path d="M12 20V4m-6 6 6-6 6 6"/>',
        'phone'=>'<path d="m7 3 3 5-3 2c1 3 4 6 7 7l2-3 5 3c0 3-2 4-4 4C10 20 4 14 3 7c0-2 1-4 4-4Z"/>',
    ];
    if (!isset($paths[$name])) { throw new \InvalidArgumentException('Unsupported interface icon.'); }
    return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$paths[$name].'</svg>';
};
$brand = static function(string $class = '') use($base, $link, $e, $u): void { ?>
<a href="<?= $e($link('home')) ?>" class="brand <?= $e($class) ?>" aria-label="Londra Transfers · <?= $e($u['home']) ?>"><span class="emblem-stack" aria-hidden="true"><img src="<?= $e($base) ?>assets/artwork/logo-master-gold.png" class="emblem-dark" width="1224" height="306" alt=""><img src="<?= $e($base) ?>assets/artwork/logo-master-black.png" class="emblem-light" width="1224" height="306" alt=""></span><span class="wordmark">LONDRA<span>TRANSFERS</span></span></a>
<?php };
$renderContactMethods = static function() use($v, $u, $e, $arrow): void { ?>
<div class="contact-methods">
<a href="tel:<?= $e($v['contact']['phone_link']) ?>"><span><?= $e($u['phone']) ?></span><strong><?= $e($v['contact']['phone_display']) ?></strong><?= $arrow() ?></a>
<a href="mailto:<?= $e($v['contact']['email']) ?>"><span><?= $e($u['email']) ?></span><strong><?= $e($v['contact']['email']) ?></strong><?= $arrow() ?></a>
<a href="https://wa.me/<?= $e(ltrim($v['contact']['phone_link'], '+')) ?>" rel="noopener noreferrer"><span><?= $e($u['contact_direct']) ?></span><strong>WhatsApp</strong><?= $arrow() ?></a>
</div>
<?php };
$renderSidebarNavigation = static function() use($v, $cat, $p, $u, $e, $icon): void {
    $groups = ['service'=>'services', 'vehicle'=>'fleet', 'airport'=>'airports', 'tour'=>'tours'];
    $activeVehicles = array_fill_keys(array_column($v['vehicles'], 'page_key'), true);
?>
<nav class="sidebar-navigation" aria-label="<?= $e($u['menu_explore']) ?>">
<?php if(isset($cat['home'])): ?><a class="sidebar-home" href="<?= $e($cat['home']['url']) ?>" <?= $p['key'] === 'home' ? 'aria-current="page"' : '' ?>><?= $e($u['home']) ?></a><?php endif; ?>
<?php foreach($groups as $kind=>$collection): $items = array_filter($cat, static fn($item) => $item['kind'] === $kind && ($kind !== 'vehicle' || isset($activeVehicles[$item['key']]))); if(!$items && !isset($cat[$collection])) { continue; } ?>
<details class="sidebar-group" data-navigation-group="<?= $e($kind) ?>" <?= $p['kind'] === $kind || $p['key'] === $collection || ($p['kind'] === 'home' && $kind === 'service') ? 'open' : '' ?>><summary><span><?= $e($u[$collection]) ?></span><?= $icon('chevron') ?></summary><div class="sidebar-group-links">
<?php if(isset($cat[$collection])): ?><a class="sidebar-overview" href="<?= $e($cat[$collection]['url']) ?>" <?= $p['key'] === $collection ? 'aria-current="page"' : '' ?>><?= $e($cat[$collection]['title']) ?></a><?php endif; ?>
<?php foreach($items as $item): ?><a href="<?= $e($item['url']) ?>" <?= $p['key'] === $item['key'] ? 'aria-current="page"' : '' ?>><?= $e($item['title']) ?></a><?php endforeach; ?>
</div></details>
<?php endforeach; ?>
<?php $company = array_filter($cat, static fn($item) => !in_array($item['kind'], ['home','service','vehicle','airport','tour','policy','privacy-request'], true) && !in_array($item['key'], ['services','fleet','airports','tours'], true)); if($company): ?>
<details class="sidebar-group" data-navigation-group="company" <?= isset($company[$p['key']]) ? 'open' : '' ?>><summary><span><?= $e($u['footer_company']) ?></span><?= $icon('chevron') ?></summary><div class="sidebar-group-links">
<?php foreach($company as $item): ?><a href="<?= $e($item['url']) ?>" <?= $p['key'] === $item['key'] ? 'aria-current="page"' : '' ?>><?= $e($item['title']) ?></a><?php endforeach; ?>
</div></details>
<?php endif; ?>
<?php $policies = array_filter($cat, static fn($item) => in_array($item['kind'], ['policy','privacy-request'], true)); if($policies): ?>
<details class="sidebar-group" data-navigation-group="policy" <?= isset($policies[$p['key']]) ? 'open' : '' ?>><summary><span><?= $e($u['navigation_policies']) ?></span><?= $icon('chevron') ?></summary><div class="sidebar-group-links">
<?php foreach($policies as $item): ?><a href="<?= $e($item['url']) ?>" <?= $p['key'] === $item['key'] ? 'aria-current="page"' : '' ?>><?= $e($item['title']) ?></a><?php endforeach; ?>
</div></details>
<?php endif; ?>
</nav>
<?php };
$renderSidebarContact = static function() use($v, $u, $e, $arrow, $cta): void { ?>
<div class="sidebar-contact-links">
<a href="tel:<?= $e($v['contact']['phone_link']) ?>"><span><?= $e($u['phone']) ?></span><strong><?= $e($v['contact']['phone_display']) ?></strong></a>
<a href="https://wa.me/<?= $e(ltrim($v['contact']['phone_link'], '+')) ?>" rel="noopener noreferrer"><span><?= $e($u['contact_direct']) ?></span><strong>WhatsApp</strong></a>
<a href="mailto:<?= $e($v['contact']['email']) ?>"><span><?= $e($u['email']) ?></span><strong><?= $e($v['contact']['email']) ?></strong></a>
<a href="https://www.instagram.com/<?= $e($v['contact']['instagram']) ?>/" rel="noopener noreferrer"><span>Instagram</span><?= $arrow() ?></a>
</div>
<?= $cta($u['enquire_short'], 'button sidebar-enquiry') ?>
<?php };
$renderForm = static function(string $type, string $id) use($v, $u, $p, $e, $link): void {
    $available = !empty($v['accept_inquiries']);
    $old = $v['old'];
    $submissionId = $v['offline'] ? $v['submission_id'] : (string) \Illuminate\Support\Str::uuid();
?>
<form action="<?= $e($v['form_action']) ?>" method="post" data-enquiry-form data-inquiries-available="<?= $available ? 'true' : 'false' ?>" data-token-url="<?= $e('/'.$v['locale'].'/form-tokens') ?>" class="enquiry-form" <?= $available ? '' : 'aria-describedby="'.$e($id).'-availability"' ?>>
<input type="hidden" name="_token" value="<?= $e($v['csrf']) ?>"><input type="hidden" name="submission_id" value="<?= $e($submissionId) ?>"><input type="hidden" name="form_token" value="<?= $e($v['form_token']) ?>"><input type="hidden" name="type" value="<?= $e($type) ?>"><input type="hidden" name="page_key" value="<?= $e($p['key']) ?>">
<?php if($type === 'booking'): ?><input type="hidden" name="service_key" value="<?= $e($p['kind'] === 'service' ? $p['key'] : '') ?>"><?php endif; ?>
<div class="honeypot" aria-hidden="true"><label for="<?= $e($id) ?>-website">Website</label><input id="<?= $e($id) ?>-website" name="website" tabindex="-1" autocomplete="off"></div>
<?php if(!$available): ?><p id="<?= $e($id) ?>-availability" class="form-availability" role="status"><?= $e($u['forms_unavailable']) ?></p><?php endif; ?>
<fieldset class="form-controls" <?= $available ? '' : 'disabled aria-describedby="'.$e($id).'-availability"' ?>><legend class="visually-hidden"><?= $e($u['form_title']) ?></legend>
<?php foreach(['name'=>'text', 'email'=>'email', 'phone'=>'tel'] as $name=>$kind): ?>
<div class="form-field"><label for="<?= $e($id.'-'.$name) ?>"><?= $e($u[$name]) ?></label><input id="<?= $e($id.'-'.$name) ?>" name="<?= $e($name) ?>" type="<?= $e($kind) ?>" value="<?= $e($old[$name] ?? '') ?>" autocomplete="<?= $e($name === 'phone' ? 'tel' : $name) ?>" required maxlength="<?= $name === 'email' ? '254' : ($name === 'name' ? '120' : '40') ?>" <?= !empty($v['errors'][$name]) ? 'aria-invalid="true"' : '' ?> aria-describedby="<?= $e($id.'-'.$name) ?>-error"><span class="field-error" id="<?= $e($id.'-'.$name) ?>-error" data-field-error="<?= $e($name) ?>"><?= $e($v['errors'][$name][0] ?? '') ?></span></div>
<?php endforeach; ?>
<?php if(isset($v['links']['privacy'])): ?><p class="form-note"><a href="<?= $e($link('privacy')) ?>"><?= $e($u['privacy_notice']) ?></a></p><?php endif; ?>
<?php if($available): ?><p class="form-note"><?= $e($u['form_note']) ?></p><?php endif; ?>
<button type="submit" class="button form-submit" <?= $available ? '' : 'disabled' ?>><?= $e($u['send']) ?><?= Html::arrow() ?></button>
</fieldset>
<p class="form-status" data-form-status role="status" aria-live="polite"><?php if($v['receipt']): ?><?= $e($u['received'].' '.$u['reference'].': '.($v['receipt']['reference'] ?? '')) ?><?php else: foreach($v['errors'] as $field=>$messages): if(in_array($field, ['name','email','phone'], true)) { continue; } ?><?= $e($messages[0] ?? '') ?><?php endforeach; endif; ?></p><a class="text-link form-reload" data-form-reload href="<?= $e($p['url']) ?>" hidden><?= $e($u['reload_form']) ?><?= Html::arrow() ?></a>
</form>
<?php };
$sectionTitle = static function(string $kicker, string $title, string $intro = '', string $id = '') use($e): void { ?>
<div class="section-heading"><div><p class="eyebrow"><?= $e($kicker) ?></p><h2 <?= $id ? 'id="'.$e($id).'"' : '' ?>><?= $e($title) ?></h2></div><?php if($intro): ?><p class="section-intro"><?= $e($intro) ?></p><?php endif; ?></div>
<?php };
$renderFleet = static function(string $id = 'fleet-deck', int $headingLevel = 3) use($v, $cat, $e, $u, $photos, $icon, $namedLabel, $luggageFacts): void {
    $vehicles = array_values(array_filter($v['vehicles'], static fn($item) => isset($cat[$item['page_key']])));
    if (!$vehicles) { return; }
    $heading = $headingLevel === 2 ? 'h2' : 'h3';
?>
<div class="fleet-deck" data-deck data-initial="0">
<div class="fleet-selection"><p><?= $e($u['fleet_notes']) ?></p><button class="text-link compare-trigger" type="button" data-open-compare><?= $e($u['fleet_compare']) ?><?= $icon('plus') ?></button></div>
<div class="fleet-tabs" data-tablist aria-label="<?= $e($u['fleet_selection']) ?>">
<?php foreach($vehicles as $i=>$vehicle): $item = $cat[$vehicle['page_key']]; ?>
<button class="fleet-tab" type="button" data-tab="<?= $i ?>" id="<?= $e($id) ?>-tab-<?= $i ?>" aria-controls="<?= $e($id) ?>-panel-<?= $i ?>"><span><?= $e(str_replace('Mercedes-Benz ', '', $item['title'])) ?></span><small><?= (int) $vehicle['passengers'] ?> <?= $e($u['passengers']) ?></small></button>
<?php endforeach; ?>
</div>
<?php foreach($vehicles as $i=>$vehicle): $item = $cat[$vehicle['page_key']]; $image = $photos($vehicle['image_key'], '', false, '(min-width: 1480px) 820px, (min-width: 1000px) 60vw, 100vw'); ?>
<div class="fleet-panel <?= $image ? '' : 'is-text-only' ?>" id="<?= $e($id) ?>-panel-<?= $i ?>" data-panel="<?= $i ?>" aria-labelledby="<?= $e($id) ?>-tab-<?= $i ?>">
<div class="fleet-copy"><p class="eyebrow"><?= $e($u['fleet_maker']) ?></p><<?= $heading ?>><?= $e(str_replace('Mercedes-Benz ', '', $item['title'])) ?></<?= $heading ?>><div class="capacity"><?= $icon('person') ?><strong><?= (int) $vehicle['passengers'] ?></strong><span><?= $e($u['passengers']) ?></span></div><p><?= $e($item['intro']) ?></p>
<?= $luggageFacts($vehicle) ?>
<?php if($item['highlights']): ?><ul class="fleet-facts"><?php foreach(array_slice($item['highlights'], 0, 4) as $highlight): ?><li><?= $e($highlight) ?></li><?php endforeach; ?></ul><?php endif; ?>
<a class="text-link" href="<?= $e($item['url']) ?>"><?= $e($namedLabel('view_named_vehicle', str_replace('Mercedes-Benz ', '', $item['title']))) ?><?= Html::arrow() ?></a></div>
<?php if($image): ?><div class="fleet-visual"><figure class="fleet-photo media-frame"><?= $image ?></figure><?php if(!empty($v['fleet_gallery_available'][$vehicle['code']])): ?><a class="fleet-gallery-direct text-link" href="<?= $e($item['url']) ?>#fleet-angles-heading" aria-label="<?= $e($u['gallery_title'].' · '.$item['title']) ?>"><?= $e($u['gallery_title']) ?><?= $icon('plus') ?></a><?php else: ?><button class="photo-open text-link" type="button" data-open-gallery data-vehicle-name="<?= $e($item['title']) ?>" aria-label="<?= $e($u['fleet_image'].' · '.$item['title']) ?>"><?= $e($u['fleet_image']) ?><?= $icon('plus') ?></button><?php endif; ?></div><?php endif; ?>
</div>
<?php endforeach; ?>
<div class="fleet-bottom"><p><?= $e($u['fleet_browse']) ?></p><div class="fleet-controls"><button class="circle-button" type="button" data-deck-prev aria-label="<?= $e($u['previous_vehicle']) ?>"><?= $icon('back') ?></button><button class="circle-button" type="button" data-deck-next aria-label="<?= $e($u['next_vehicle']) ?>"><?= Html::arrow() ?></button></div></div>
</div>
<?php };
$renderServiceIndex = static function(array $items) use($e, $photos, $u, $namedLabel): void { if (!$items) { return; } ?>
<div class="service-collection">
<?php foreach(array_values($items) as $item):
    $body = trim((preg_split('/\n\s*\n/u', (string) ($item['body'] ?? '')) ?: [])[0] ?? '');
    $image = $photos($item['image_key'], '', false, '(min-width: 1480px) 610px, (min-width: 650px) 45vw, 100vw');
?>
<article class="service-spread <?= $image ? '' : 'is-text-only' ?>" id="service-<?= $e($item['key']) ?>">
<div class="spread-copy"><p class="eyebrow"><?= $e($item['eyebrow'] ?: $u['signature']) ?></p><h2><a href="<?= $e($item['url']) ?>"><?= $e($item['title']) ?></a></h2><p class="spread-intro"><?= $e($item['intro']) ?></p>
<?php if($body && $body !== $item['intro']): ?><p><?= $e($body) ?></p><?php endif; ?>
<?php if($item['highlights']): ?><ul class="spread-highlights"><?php foreach(array_slice($item['highlights'], 0, 4) as $highlight): ?><li><?= $e($highlight) ?></li><?php endforeach; ?></ul><?php endif; ?>
<a class="text-link" href="<?= $e($item['url']) ?>"><?= $e($namedLabel('explore_named', $item['title'])) ?><?= Html::arrow() ?></a></div>
<?php if($image): ?><a class="spread-photo media-frame" href="<?= $e($item['url']) ?>" tabindex="-1" aria-hidden="true"><?= $image ?></a><?php endif; ?>
</article>
<?php endforeach; ?>
</div>
<?php };
$renderProcess = static function() use($e, $u): void { ?>
<ol class="process-grid"><?php for($i = 1; $i <= 3; $i++): ?><li><span aria-hidden="true">0<?= $i ?></span><div><h3><?= $e($u['process_'.$i]) ?></h3><p><?= $e($u['process_detail_'.$i]) ?></p></div></li><?php endfor; ?></ol>
<?php };
