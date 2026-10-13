<?php $privacyAvailable = !empty($v['privacy_requests_allowed']); ?>
<section class="contact-section section wrap"><div class="contact-copy"><p class="eyebrow"><?= $e($p['eyebrow']) ?></p><h1><?= $e($p['title']) ?></h1><p class="lede"><?= $e($p['intro']) ?></p>
<?php foreach($paragraphs as $paragraph): ?><p class="contact-detail"><?= $e($paragraph) ?></p><?php endforeach; ?>
<a class="text-link" href="mailto:<?= $e($v['contact']['email']) ?>"><?= $e($v['contact']['email']) ?><?= $arrow() ?></a></div><div class="contact-paper"><h2><?= $e($u['privacy_form_title']) ?></h2>
<?php if(!empty($v['privacy_receipt'])): ?><p role="status"><?= $e($v['privacy_receipt']) ?></p><?php endif; ?>
<form class="enquiry-form" data-privacy-form data-privacy-available="<?= $privacyAvailable ? 'true' : 'false' ?>" method="post" action="/<?= $e($v['locale']) ?>/privacy-requests" <?= $privacyAvailable ? '' : 'aria-describedby="privacy-availability"' ?>><input type="hidden" name="_token" value="<?= $e($v['csrf']) ?>"><input type="hidden" name="submission_id" value="<?= $e($v['submission_id']) ?>"><input type="hidden" name="form_token" value="<?= $e($v['privacy_form_token'] ?? '') ?>">
<div class="honeypot" aria-hidden="true"><label for="privacy-website">Website</label><input id="privacy-website" name="website" tabindex="-1" autocomplete="off"></div>
<?php if(!$privacyAvailable): ?><p id="privacy-availability" class="form-availability" role="status"><?= $e($u['forms_unavailable']) ?></p><?php endif; ?>
<fieldset class="form-controls" <?= $privacyAvailable ? '' : 'disabled aria-describedby="privacy-availability"' ?>><legend class="visually-hidden"><?= $e($u['privacy_form_title']) ?></legend>
<div class="form-field"><label for="privacy-email"><?= $e($u['email']) ?></label><input id="privacy-email" name="email" type="email" autocomplete="email" maxlength="254" required value="<?= $e($v['old']['email'] ?? '') ?>" <?= !empty($v['errors']['email']) ? 'aria-invalid="true"' : '' ?> aria-describedby="privacy-email-error<?= $privacyAvailable ? ' privacy-email-hint' : '' ?>"><span id="privacy-email-error" class="field-error"><?= $e($v['errors']['email'][0] ?? '') ?></span></div>
<?php if($privacyAvailable): ?><p id="privacy-email-hint" class="form-note"><?= $e($u['privacy_email_hint']) ?></p><?php endif; ?>
<button class="button form-submit" type="submit" <?= $privacyAvailable ? '' : 'disabled' ?>><?= $e($u['privacy_submit']) ?></button></fieldset>
<?php if(isset($v['links']['privacy'])): ?><a class="text-link" href="<?= $e($link('privacy')) ?>"><?= $e($u['privacy_notice']) ?></a><?php endif; ?>
<?php foreach($v['errors'] as $field=>$messages): if($field === 'email') { continue; } ?><p class="form-status" role="alert"><?= $e($messages[0] ?? '') ?></p><?php endforeach; ?>
</form>
<?php if(!$privacyAvailable): ?><div class="privacy-direct"><p><?= $e($u['privacy_email_alternative']) ?></p><a class="text-link" href="mailto:<?= $e($v['contact']['email']) ?>"><?= $e($v['contact']['email']) ?><?= $arrow() ?></a><a class="text-link" href="tel:<?= $e($v['contact']['phone_link']) ?>"><?= $e($v['contact']['phone_display']) ?><?= $arrow() ?></a></div><?php endif; ?>
</div></section>
