<?php
$phone = trim((string) setting('contact.phone', ''));
$phone2 = trim((string) setting('contact.phone_secondary', ''));
$email = trim((string) setting('contact.email', ''));
$address = trim((string) setting('contact.address', ''));
$tel = static fn(string $n): string => 'tel:' . preg_replace('/[^0-9+]/', '', $n);
$col1 = [['Home', '/'], ['About Us', '/about'], ['Programs', '/programs'], ['Our Impact', '/impact']];
$col2 = [['Gallery', '/gallery'], ['News', '/news'], ['Get Involved', '/get-involved'], ['Contact', '/contact']];
$strap = trim((string) setting('footer.strapline', ''));
?>
<footer class="site-footer">
  <div class="container site-footer__grid">
    <div class="site-footer__about">
      <?= $this->insert('partials/brand', ['variant' => 'light']) ?>
<?php if (trim((string) setting('footer.text', '')) !== ''): ?>
      <p><?= e(setting('footer.text')) ?></p>
<?php endif; ?>
    </div>
    <nav aria-labelledby="footer-links-h">
      <h2 id="footer-links-h">Quick Links</h2>
      <div class="footer-links">
        <ul><?php foreach ($col1 as [$l, $h]): ?><li><a href="<?= e(url($h)) ?>"><?= e($l) ?></a></li><?php endforeach; ?></ul>
        <ul><?php foreach ($col2 as [$l, $h]): ?><li><a href="<?= e(url($h)) ?>"><?= e($l) ?></a></li><?php endforeach; ?></ul>
      </div>
    </nav>
    <div>
      <h2>Contact Us</h2>
<?php if ($phone !== '' || $email !== '' || $address !== ''): ?>
      <ul class="footer-contact">
<?php if ($phone !== ''): ?>
        <li><?= icon('telephone-fill') ?><span><a href="<?= e($tel($phone)) ?>"><?= e($phone) ?></a><?php if ($phone2 !== ''): ?><br><a href="<?= e($tel($phone2)) ?>"><?= e($phone2) ?></a><?php endif; ?></span></li>
<?php endif; ?>
<?php if ($email !== ''): ?>
        <li><?= icon('envelope-fill') ?><a href="<?= e(mailto_url($email)) ?>"><?= e($email) ?></a></li>
<?php endif; ?>
<?php if ($address !== ''): ?>
        <li><?= icon('geo-alt-fill') ?><span><?= e($address) ?></span></li>
<?php endif; ?>
      </ul>
<?php else: ?>
      <p class="footer-strap">Contact details coming soon. In the meantime, <a href="<?= e(url('/contact')) ?>">send us a message</a>.</p>
<?php endif; ?>
    </div>
    <div>
      <h2>Follow Us</h2>
      <?= $this->insert('partials/social-links', ['variant' => 'footer']) ?>
<?php if ($strap !== ''): ?>
      <p class="footer-strap"><?= e($strap) ?></p>
<?php endif; ?>
    </div>
  </div>
  <div class="site-footer__base">
    <div class="container">
      <span>&copy; <?= date('Y') ?> RIT Foundation Ghana. All rights reserved.</span>
      <span><a href="<?= e(url('/privacy')) ?>">Privacy Policy</a> &nbsp;|&nbsp; <a href="<?= e(url('/terms')) ?>">Terms &amp; Conditions</a></span>
    </div>
  </div>
</footer>
