<?php
/** Fleet gallery: only complete, uploaded, rights-cleared selections arrive here. */
$gallery = $v['fleet_gallery'] ?? [];
if (!$vehicle || count($gallery) < 2) { return; }
$lang = $v['locale'];
$copy = [
    'en'=>['kicker'=>'The vehicle, in detail','heading'=>'Exterior & interior gallery','open'=>'View photograph','close'=>'Close gallery','previous'=>'Previous photograph','next'=>'Next photograph','image'=>'Photograph','of'=>'of'],
    'it'=>['kicker'=>'La vettura nei dettagli','heading'=>'Galleria esterni e interni','open'=>'Apri la fotografia','close'=>'Chiudi galleria','previous'=>'Fotografia precedente','next'=>'Fotografia successiva','image'=>'Fotografia','of'=>'di'],
    'es'=>['kicker'=>'El vehículo en detalle','heading'=>'Galería exterior e interior','open'=>'Ver fotografía','close'=>'Cerrar galería','previous'=>'Fotografía anterior','next'=>'Fotografía siguiente','image'=>'Fotografía','of'=>'de'],
][$lang] ?? null;
if (!$copy) { return; }
?>
<section class="fleet-angles section wrap" aria-labelledby="fleet-angles-heading">
  <header class="fleet-angles-heading">
    <p class="eyebrow"><?= $e($copy['kicker']) ?></p>
    <h2 id="fleet-angles-heading"><?= $e($copy['heading']) ?></h2>
    <p class="fleet-angles-caption"><?= $e($p['title']) ?> · <?= count($gallery) ?> <?= $e($copy['image']) ?></p>
  </header>
  <div class="fleet-angle-grid" data-fleet-angle-grid>
  <?php foreach ($gallery as $index => $photo): $image = $photos($photo['key'], '', false, '(min-width: 1200px) 370px, (min-width: 650px) 45vw, 90vw'); if (!$image) { continue; } ?>
    <figure class="fleet-angle-card">
      <button type="button" class="fleet-angle-open" data-angle-open data-angle-index="<?= (int)$index ?>" aria-haspopup="dialog" aria-controls="fleet-angles-dialog" aria-label="<?= $e($copy['open'].' · '.$photo['alt']) ?>">
        <span class="fleet-angle-frame"><?= $image ?></span>
        <span class="fleet-angle-bottom"><span class="fleet-angle-ref"><?= $e($photo['reference']) ?></span><span class="fleet-angle-title"><?= $e($photo['alt']) ?></span><span class="fleet-angle-plus" aria-hidden="true">+</span></span>
      </button>
    </figure>
  <?php endforeach; ?>
  </div>
</section>
<dialog id="fleet-angles-dialog" class="fleet-angles-dialog" aria-labelledby="fleet-angles-dialog-heading" data-fleet-angles-dialog>
  <div class="fleet-angle-dialog-bar">
    <h2 id="fleet-angles-dialog-heading" tabindex="-1"><?= $e($copy['heading']) ?></h2>
    <button type="button" class="fleet-angle-dialog-close" data-angle-close><?= $e($copy['close']) ?> <span aria-hidden="true">×</span></button>
  </div>
  <figure class="fleet-angle-stage">
    <img src="" alt="" width="1600" height="900" data-angle-large>
    <figcaption data-angle-caption></figcaption>
  </figure>
  <div class="fleet-angle-dialog-footer">
    <button type="button" data-angle-prev aria-label="<?= $e($copy['previous']) ?>"><?= $e($copy['previous']) ?></button>
    <span aria-live="polite" data-angle-position></span>
    <button type="button" data-angle-next aria-label="<?= $e($copy['next']) ?>"><?= $e($copy['next']) ?></button>
  </div>
</dialog>
