<?php
/** @var string $variant '' | 'light' */
$variant = $variant ?? '';
$logo = (string) setting('site.logo_path', '');
$motto = trim((string) setting('org.motto', ''));
?>
<a class="brand<?= $variant === 'light' ? ' brand--light' : '' ?>" href="<?= e(url('/')) ?>"<?= $variant === 'light' ? '' : ' aria-label="RIT Foundation Ghana, home"' ?>>
<?php if ($logo !== ''): ?>
  <img class="brand__logo" src="<?= e(upload_url($logo)) ?>" alt="<?= e((string) setting('site.logo_alt', 'RIT Foundation Ghana')) ?>" height="56">
<?php else: ?>
  <span class="brand__emblem" aria-hidden="true">R</span>
  <span class="brand__text"><b>RIT</b>Foundation<br>Ghana<?php if ($motto !== ''): ?><span class="brand__motto"><?= e($motto) ?></span><?php endif; ?></span>
<?php endif; ?>
</a>
