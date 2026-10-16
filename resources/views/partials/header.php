<?php
$phone = trim((string) setting('contact.phone', ''));
$phone2 = trim((string) setting('contact.phone_secondary', ''));
$email = trim((string) setting('contact.email', ''));
$address = trim((string) setting('contact.address', ''));
$tel = static fn(string $n): string => 'tel:' . preg_replace('/[^0-9+]/', '', $n);
$nav = [
    ['Home', '/'], ['About Us', '/about'], ['Programs', '/programs'], ['Our Impact', '/impact'],
    ['Gallery', '/gallery'], ['News', '/news'], ['Get Involved', '/get-involved'], ['Contact', '/contact'],
];
?>
<div class="utility-bar">
  <div class="container utility-bar__inner">
<?php if ($phone !== '' || $email !== '' || $address !== ''): ?>
    <ul class="utility-bar__items">
<?php if ($phone !== ''): ?>
      <li class="is-core"><?= icon('telephone-fill') ?><span><a href="<?= e($tel($phone)) ?>"><?= e($phone) ?></a><?php if ($phone2 !== ''): ?><span class="utility-bar__sep" aria-hidden="true">|</span><a href="<?= e($tel($phone2)) ?>"><?= e($phone2) ?></a><?php endif; ?></span></li>
<?php endif; ?>
<?php if ($email !== ''): ?>
      <li class="is-core"><?= icon('envelope-fill') ?><a href="<?= e(mailto_url($email)) ?>"><?= e($email) ?></a></li>
<?php endif; ?>
<?php if ($address !== ''): ?>
      <li class="utility-bar__addr"><?= icon('geo-alt-fill') ?><span><?= e($address) ?></span></li>
<?php endif; ?>
    </ul>
<?php endif; ?>
    <?= $this->insert('partials/social-links', ['variant' => 'bar']) ?>
  </div>
</div>
<header class="site-header">
  <div class="container site-header__inner">
    <?= $this->insert('partials/brand', ['variant' => '']) ?>
    <a class="btn site-header__donate" href="<?= e(url('/donate')) ?>">Donate <?= icon('heart-fill') ?></a>
    <button type="button" class="nav-toggle" aria-controls="site-menu" aria-expanded="false">
      <?= icon('list', 'icon icon-open') ?><?= icon('x-lg', 'icon icon-close') ?><span>Menu</span>
    </button>
    <div class="site-menu" id="site-menu">
      <nav class="site-nav" aria-label="Primary">
        <ul>
<?php foreach ($nav as [$label, $href]): ?>
          <li><a href="<?= e(url($href)) ?>"<?= nav_active($href) ?>><?= e($label) ?></a></li>
<?php endforeach; ?>
        </ul>
      </nav>
    </div>
  </div>
</header>
