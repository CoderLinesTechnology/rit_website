<?php
/** vars: $title, ?$breadcrumbs, ?$eyebrow, ?$tagline, ?$text, ?$image (uploads path), ?$imageAlt */
$image = (string) ($image ?? '');
?>
<section class="hero hero--inner<?= $image === '' ? ' hero--noimg' : '' ?>">
<?php if ($image !== ''): ?>
  <img class="hero__img" src="<?= e(upload_url($image)) ?>"<?= srcset($image, '100vw') ?> alt="<?= e((string) ($imageAlt ?? '')) ?>" width="1600" height="500" decoding="async" fetchpriority="high">
<?php endif; ?>
  <div class="container hero__inner">
    <div class="hero__copy">
      <?= $this->insert('partials/breadcrumbs', ['breadcrumbs' => $breadcrumbs ?? []]) ?>
<?php if (!empty($eyebrow)): ?>
      <span class="eyebrow"><?= e($eyebrow) ?></span>
<?php endif; ?>
      <h1><?= e($title) ?></h1>
<?php if (!empty($tagline)): ?>
      <p class="hero__tagline"><?= e($tagline) ?></p>
<?php endif; ?>
<?php if (!empty($text)): ?>
      <p class="hero__text"><?= e($text) ?></p>
<?php endif; ?>
    </div>
  </div>
</section>
