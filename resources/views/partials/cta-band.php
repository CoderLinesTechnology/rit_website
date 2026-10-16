<?php
/** vars: $icon, ?$eyebrow, $heading, ?$text, $buttonLabel, $buttonHref, ?$image (defaults to site.cta_image), ?$imageAlt, ?$script */
$image = isset($image) ? (string) $image : (string) setting('site.cta_image', '');
$imageAlt = isset($imageAlt) ? (string) $imageAlt : (string) setting('site.cta_image_alt', '');
?>
<section class="cta-band" aria-label="<?= e($heading) ?>">
  <div class="cta-band__copy">
    <?= icon($icon ?? 'people') ?>
    <div>
<?php if (!empty($eyebrow)): ?><span class="eyebrow"><?= e($eyebrow) ?></span><?php endif; ?>
      <h2><?= e($heading) ?></h2>
<?php if (!empty($text)): ?><p><?= e($text) ?></p><?php endif; ?>
      <a class="btn btn--light" href="<?= e(url($buttonHref)) ?>"><?= e($buttonLabel) ?> <?= icon('arrow-right') ?></a>
    </div>
  </div>
  <div class="cta-band__media">
<?php if ($image !== ''): ?>
    <img src="<?= e(upload_url($image)) ?>"<?= srcset($image, '(min-width: 62rem) 50vw, 100vw') ?> alt="<?= e($imageAlt) ?>" width="900" height="400" loading="lazy" decoding="async">
<?php else: ?>
    <div class="cta-band__pattern" aria-hidden="true"></div>
<?php endif; ?>
<?php if (!empty($script)): ?>
    <p class="cta-band__script"><?= e($script) ?></p>
<?php endif; ?>
  </div>
</section>
