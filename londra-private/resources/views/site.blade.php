<?php require $v['template_root'].'/components.php'; ?>
<!doctype html>
<html lang="<?= $e($v['locale']) ?>" data-theme="dark">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="dark light"><meta name="theme-color" content="#0B0B0B">
<title><?= $e($p['seo_title']) ?></title><meta name="description" content="<?= $e($p['meta_description']) ?>"><meta name="robots" content="<?= $v['indexable']?'index,follow,max-image-preview:large':'noindex,nofollow,noarchive' ?>"><link rel="canonical" href="<?= $e($v['canonical']) ?>">
<?php foreach($v['alternates'] as $locale=>$url): ?><link rel="alternate" hreflang="<?= $e($locale) ?>" href="<?= $e($url) ?>"><?php endforeach; ?>
<link rel="alternate" hreflang="x-default" href="<?= $e($p['key']==='home'?$v['origin'].'/':($v['alternates']['en']??$v['canonical'])) ?>">
<meta property="og:type" content="website"><meta property="og:site_name" content="Londra Transfers"><meta property="og:title" content="<?= $e($p['seo_title']) ?>"><meta property="og:description" content="<?= $e($p['meta_description']) ?>"><meta property="og:url" content="<?= $e($v['canonical']) ?>"><meta property="og:locale" content="<?= $e(['en'=>'en_GB','it'=>'it_IT','es'=>'es_ES'][$v['locale']]) ?>">
<?php $sharingImage=\App\Presentation\Html::imageUrl($v,$p['image_key']); if($sharingImage!==null): ?><meta property="og:image" content="<?= $e($sharingImage) ?>"><meta property="og:image:alt" content="<?= $e($v['media'][$p['image_key']]['alt'][$v['locale']]??'') ?>"><?php endif; ?>
<meta name="twitter:card" content="summary_large_image"><link rel="icon" type="image/svg+xml" href="<?= $e(\App\Presentation\Html::asset($v,'assets/artwork/favicon.svg')) ?>"><link rel="alternate icon" type="image/png" sizes="32x32" href="<?= $e(\App\Presentation\Html::asset($v,'assets/artwork/favicon-32.png')) ?>"><link rel="apple-touch-icon" sizes="180x180" href="<?= $e(\App\Presentation\Html::asset($v,'assets/artwork/apple-touch-icon.png')) ?>">
<script src="<?= $e(\App\Presentation\Html::asset($v,'assets/theme.js')) ?>"></script><link rel="stylesheet" href="<?= $e($v['css_url']) ?>"><script <?= $v['offline']?'defer':'type="module"' ?> src="<?= $e($v['js_url']) ?>"></script>
<script type="application/ld+json"><?= $v['schema'] ?></script>
</head>
<body data-page="<?= $e($p['key']) ?>" data-kind="<?= $e($p['kind']) ?>" data-ui="<?= $e(json_encode(array_intersect_key($u,array_flip(['light','gallery_title','invalid_email','too_long','required','sending','try_again','received','reference','form_expired','form_refreshed','form_refresh_failed','forms_unavailable','retry_after','enquiry_form_used'])),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)) ?>">
<a class="skip-link" href="#main"><?= $e($u['skip']) ?></a>
<?php require $v['template_root'].'/header.php'; ?>
<div class="public-shell">
<aside class="site-sidebar sidebar-left" id="left-sidebar" aria-labelledby="left-sidebar-title"><h2 id="left-sidebar-title" class="eyebrow"><?= $e($u['menu_explore']) ?></h2><?php $renderSidebarNavigation(); ?></aside>
<main id="main" class="public-main" tabindex="-1">
<?php require $v['template_root'].($p['kind']==='home'?'/home.php':'/page.php'); ?>
<?php if(!in_array($p['kind'],['contact','enquiry','policy','privacy-request','faq','directory'],true)): ?>
<?php $invitationImage=$photos('private-transfer','',false,'(min-width: 1480px) 640px, (min-width: 650px) 45vw, 100vw'); ?>
<section class="invitation" aria-labelledby="invitation-title"><div class="invitation-grid wrap <?= $invitationImage?'':'is-text-only' ?>"><div class="invitation-copy"><p class="eyebrow"><?= $e($u['contact_kicker']) ?></p><h2 id="invitation-title"><?php if($p['key']==='partners'): ?><?= $e($u['partner_contact_title']) ?><?php else: ?><?= $e($u['invitation_first']) ?><em><?= $e($u['invitation_second']) ?></em><?php endif; ?></h2><p><?= $e($p['key']==='partners'?$u['partner_contact_intro']:$u['cta_body']) ?></p><div class="invitation-actions"><?php if($p['key']==='partners'): ?><a class="button" href="<?= $e($link('contact')) ?>"><?= $e($u['contact']) ?><?= $arrow() ?></a><?php else: ?><?= $cta($u['enquire'],'button') ?><?php endif; ?><a href="tel:<?= $e($v['contact']['phone_link']) ?>" class="text-link"><?= $e($v['contact']['phone_display']) ?><?= $arrow() ?></a></div></div><?php if($invitationImage): ?><figure class="invitation-photo media-frame"><?= $invitationImage ?></figure><?php endif; ?></div></section>
<?php endif; ?>
</main>
<aside class="site-sidebar sidebar-right" id="right-sidebar" aria-labelledby="right-sidebar-title"><h2 id="right-sidebar-title" class="eyebrow"><?= $e($u['direct_contact']) ?></h2><?php $renderSidebarContact(); ?></aside>
</div>
<?php require $v['template_root'].'/footer.php'; ?>
</body></html>
