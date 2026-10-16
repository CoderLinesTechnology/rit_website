<?php
/** vars: $reference */
$this->layout('layouts/public');
?>
<?= $this->insert('partials/page-hero', [
    'breadcrumbs' => [['Home', '/'], ['Training Applications', '/apply'], ['Application received', null]],
    'title' => 'Application received',
    'text' => 'Thank you for applying to RIT Foundation Ghana.',
]) ?>

<section class="section" aria-labelledby="thanks-h">
  <div class="container container--narrow">
    <div class="form-card form-card--lg thanks">
      <span class="thanks__icon"><?= icon('check-circle') ?></span>
      <h2 id="thanks-h">We have received your application</h2>
      <p class="thanks__label">Your reference number</p>
      <p class="reference" id="reference"><?= e($reference) ?></p>
      <p>Please keep this number and quote it if you contact us about your application.</p>
<?php if (\App\Services\Settings::bool('notify.send_confirmations')): ?>
      <p>We have queued a confirmation email to the address you gave.</p>
<?php endif; ?>

      <h3>What happens next</h3>
      <ol class="thanks__list">
        <li>The Foundation reviews your application.</li>
        <li>If you are selected, we will contact you with further instructions about your training program.</li>
      </ol>
      <p class="notice notice--strong"><?= icon('info-circle') ?><span><strong>Submitting an application does not guarantee a place on a program.</strong></span></p>

      <div class="form__actions">
        <a class="btn" href="<?= e(url('/programs')) ?>">View our programs <?= icon('arrow-right') ?></a>
        <a class="btn btn--secondary" href="<?= e(url('/')) ?>">Back to home</a>
      </div>
    </div>
  </div>
</section>
