<?php
/** vars: $donation (row of the donations table) */
$this->layout('layouts/public');
$status = (string) ($donation['status'] ?? '');
$verified = $status === 'successful' && !empty($donation['verified_at']);
if ($verified) {
    $state = 'success'; $ico = 'check-circle'; $heading = 'Thank you';
    $text = 'Thank you — your donation of ' . money((int) $donation['amount_minor'], (string) $donation['currency']) . ' has been confirmed.';
} elseif (in_array($status, ['failed', 'abandoned'], true)) {
    $state = 'failed'; $ico = 'info-circle'; $heading = 'Payment not completed';
    $text = 'Your payment was not completed. No money was taken by us.';
} elseif (in_array($status, ['initiated', 'pending'], true)) {
    $state = 'pending'; $ico = 'clock-history'; $heading = 'Waiting for confirmation';
    $text = 'We are waiting for the payment provider to confirm your donation. This page will not change by itself; please check back shortly.';
} else {
    $state = 'pending'; $ico = 'info-circle'; $heading = 'Donation status';
    $text = 'We could not confirm this donation. If you believe you were charged, please contact us and quote the reference below.';
}
?>
<?= $this->insert('partials/page-hero', [
    'breadcrumbs' => [['Home', '/'], ['Donate', '/donate'], ['Your donation', null]],
    'title' => 'Your donation',
]) ?>

<section class="section" aria-labelledby="status-h">
  <div class="container container--narrow">
    <div class="form-card form-card--lg thanks thanks--<?= e($state) ?>">
      <span class="thanks__icon"><?= icon($ico) ?></span>
      <h2 id="status-h"><?= e($heading) ?></h2>
      <p role="status"><?= e($text) ?></p>
      <p class="thanks__label">Donation reference</p>
      <p class="reference"><?= e((string) $donation['reference']) ?></p>
      <div class="form__actions">
<?php if ($state === 'failed'): ?>
        <a class="btn" href="<?= e(url('/donate')) ?>">Try again <?= icon('arrow-right') ?></a>
<?php endif; ?>
        <a class="btn btn--secondary" href="<?= e(url('/')) ?>">Back to home</a>
      </div>
    </div>
  </div>
</section>
