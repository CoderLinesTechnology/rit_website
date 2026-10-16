<?php
/** vars: none */
// Title, description, canonical and share image come from the controller (SEO settings).
$this->layout('layouts/public');
$body = trim(setting('privacy.policy'));
?>
<?= $this->insert('partials/page-hero', ['breadcrumbs' => [['Home', '/'], ['Privacy Notice', null]], 'title' => 'Privacy Notice']) ?>

<section class="section">
  <div class="container container--narrow">
<?php if ($body !== ''): ?>
    <div class="prose"><?= format_text($body) ?></div>
<?php else: ?>
    <p>This page is being prepared. Please contact us with any questions.</p>
<?php endif; ?>
    <div class="legal-contact">
      <h2>Contact</h2>
      <p><strong><?= e(setting('org.legal_name')) ?></strong><br>
<?php if (setting('contact.postal_address') !== ''): ?>
        <?= e(setting('contact.postal_address')) ?><br>
<?php endif; ?>
<?php if (setting('contact.email') !== ''): ?>
        <a href="<?= e(mailto_url(setting('contact.email'))) ?>"><?= e(setting('contact.email')) ?></a>
<?php endif; ?>
      </p>
    </div>
  </div>
</section>
