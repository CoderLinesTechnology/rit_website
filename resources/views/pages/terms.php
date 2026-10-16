<?php
/** vars: none */
// Title, description, canonical and share image come from the controller (SEO settings).
$this->layout('layouts/public');
$body = trim(setting('terms.content'));
?>
<?= $this->insert('partials/page-hero', ['breadcrumbs' => [['Home', '/'], ['Terms & Conditions', null]], 'title' => 'Terms & Conditions']) ?>

<section class="section">
  <div class="container container--narrow">
<?php if ($body !== ''): ?>
    <div class="prose"><?= format_text($body) ?></div>
<?php else: ?>
    <p>This page is being prepared. Please contact us with any questions.</p>
<?php endif; ?>
  </div>
</section>
