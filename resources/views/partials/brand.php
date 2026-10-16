<?php
/**
 * The logo. vars: $variant '' (light backgrounds) | 'light' (dark backgrounds: white lettering).
 * Uses an uploaded logo when one is set in Admin › Website content › Logo, otherwise the
 * official artwork shipped in public/assets/images (logo.webp / logo-light.webp, PNG fallback).
 */
$variant = $variant ?? '';
$dark = $variant === 'light';
$uploaded = (string) setting($dark ? 'site.logo_light_path' : 'site.logo_path', '');
$alt = (string) setting('site.logo_alt', 'RIT Foundation Ghana');
$file = $dark ? 'logo-light' : 'logo';
?>
<a class="brand<?= $dark ? ' brand--light' : '' ?>" href="<?= e(url('/')) ?>"<?= $dark ? '' : ' aria-label="RIT Foundation Ghana, home"' ?>>
<?php if (upload_url($uploaded) !== ''): ?>
  <img class="brand__logo" src="<?= e(upload_url($uploaded)) ?>" alt="<?= e($alt) ?>" height="64">
<?php else: ?>
  <picture>
    <source srcset="<?= e(asset('images/' . $file . '.webp')) ?>" type="image/webp">
    <img class="brand__logo" src="<?= e(asset('images/' . $file . '.png')) ?>" alt="<?= e($alt) ?>" width="311" height="192" decoding="async"<?= $dark ? ' loading="lazy"' : '' ?>>
  </picture>
<?php endif; ?>
</a>
