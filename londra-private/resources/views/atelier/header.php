<header class="site-header" id="top"><div class="header-inner wrap">
<?php $brand('header-brand'); ?>
<nav class="header-nav" aria-label="<?= $e($u['menu']) ?>">
<?php foreach(['services','airports','fleet','tours','about','contact'] as $key): if(!isset($cat[$key])) { continue; } ?><a href="<?= $e($link($key)) ?>" <?= $p['key'] === $key ? 'aria-current="page"' : '' ?>><?= $e($u[$key]) ?></a><?php endforeach; ?>
</nav>
<div class="header-tools"><details class="language-switch"><summary aria-label="<?= $e($u['language']) ?>"><span><?= $e(strtoupper($v['locale'])) ?></span><?= $icon('chevron') ?></summary><nav aria-label="<?= $e($u['language']) ?>">
<?php foreach(['en'=>'English','it'=>'Italiano','es'=>'Español'] as $code=>$name): if(!isset($v['language_choices'][$code])) { continue; } ?><a href="<?= $e($v['language_choices'][$code]) ?>" lang="<?= $e($code) ?>" hreflang="<?= $e($code) ?>" <?= $v['locale'] === $code ? 'aria-current="true"' : '' ?>><?= $e($name) ?></a><?php endforeach; ?>
</nav></details><button class="icon-button theme-button" type="button" data-theme-toggle aria-label="<?= $e($u['light']) ?>" aria-pressed="false"><span class="sun-icon"><?= $icon('sun') ?></span><span class="moon-icon"><?= $icon('moon') ?></span></button><?= $cta($u['enquire_short'], 'button header-enquiry') ?>
<a href="<?= $e($links['directory'] ?? '#left-sidebar') ?>" class="icon-button menu-trigger" data-open-navigation aria-label="<?= $e($u['open_navigation']) ?>"><span class="menu-lines" aria-hidden="true"><i></i><i></i></span></a>
</div>
</div></header>
<div class="sidebar-mobile-controls wrap"><a class="text-link sidebar-menu-control" href="<?= $e($links['directory'] ?? '#left-sidebar') ?>" data-open-navigation><span><?= $e($u['menu']) ?></span><span class="menu-lines" aria-hidden="true"><i></i><i></i></span></a><?= $cta($u['enquire_short'], 'button sidebar-contact-control') ?></div>
<dialog id="navigation-dialog" class="navigation-dialog sidebar-drawer drawer-left" aria-labelledby="navigation-title"><div class="menu-top"><?php $brand('menu-brand'); ?><button class="icon-button" type="button" data-close-navigation aria-label="<?= $e($u['close_navigation']) ?>"><?= $icon('close') ?></button></div><div class="sidebar-drawer-content"><h2 id="navigation-title" class="eyebrow" tabindex="-1"><?= $e($u['menu_explore']) ?></h2><?php $renderSidebarNavigation(); ?><nav class="menu-languages" aria-label="<?= $e($u['language']) ?>">
<?php foreach(['en'=>'English','it'=>'Italiano','es'=>'Español'] as $code=>$name): if(!isset($v['language_choices'][$code])) { continue; } ?><a href="<?= $e($v['language_choices'][$code]) ?>" lang="<?= $e($code) ?>" hreflang="<?= $e($code) ?>" <?= $v['locale'] === $code ? 'aria-current="true"' : '' ?>><?= $e($name) ?></a><?php endforeach; ?>
</nav></div></dialog>
