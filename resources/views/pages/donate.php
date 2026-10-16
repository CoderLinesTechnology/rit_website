<?php
/** vars: $available, $nonce, $presets */
$this->layout('layouts/public');
$available = !empty($available); $presets = $presets ?? [];
$offline = trim(setting('donations.offline_instructions'));
$amountChoice = old('amount', '100');
$amountErr = field_error('amount'); $otherErr = field_error('amount_other');
?>
<?= $this->insert('partials/page-hero', [
    'breadcrumbs' => [['Home', '/'], ['Donate', null]],
    'eyebrow' => (string) setting('donate.hero_eyebrow', ''),
    'title' => (string) setting('donate.hero_heading', 'Support Our Work'),
    'tagline' => (string) setting('donate.hero_tagline', ''),
    'text' => (string) setting('donate.hero_text', ''),
    'image' => (string) setting('donate.hero_image', ''),
    'imageAlt' => (string) setting('donate.hero_image_alt', ''),
]) ?>

<section class="section" aria-labelledby="donate-h">
  <div class="container container--narrow">
<?php if ($available): ?>
    <div class="form-card form-card--lg">
      <header class="form-head">
        <span class="form-head__icon"><?= icon('heart-fill') ?></span>
        <div>
          <span class="eyebrow">Make a donation</span>
          <h2 id="donate-h">Give securely online</h2>
          <p>You will pay on our payment provider’s secure page. We never see or store your card or mobile money details.</p>
        </div>
      </header>
      <?= $this->insert('partials/error-summary') ?>
      <form class="form" method="post" action="<?= e(url('/donate')) ?>" data-once data-busy-text="Sending…">
        <?= csrf_field() ?>
        <?= \App\Services\FormGuard::fields($nonce) ?>
        <fieldset class="field<?= $amountErr ? ' field--invalid' : '' ?>">
          <legend class="field__label">Amount <span class="field__req" aria-hidden="true">*</span><span class="visually-hidden">(required)</span></legend>
          <div class="amount-grid" id="field-amount">
<?php foreach ($presets as $i => $p): ?>
            <label class="amount-option"><input type="radio" name="amount" value="<?= e($p) ?>"<?= checked($amountChoice === (string) $p) ?><?= $i === 0 ? ' required' : '' ?>><span>GH₵<?= e($p) ?></span></label>
<?php endforeach; ?>
            <label class="amount-option"><input type="radio" name="amount" value="other"<?= checked($amountChoice === 'other') ?>><span>Other amount</span></label>
          </div>
<?php if ($amountErr): ?><p class="field__error"><?= e($amountErr) ?></p><?php endif; ?>
        </fieldset>
        <div data-show-when="amount=other">
          <?= $this->insert('partials/form/input', ['name' => 'amount_other', 'label' => 'Other amount (GH₵)', 'type' => 'number', 'maxlength' => 12, 'hint' => 'Between GH₵1 and GH₵1,000,000.', 'attrs' => ['min' => '1', 'max' => '1000000', 'step' => '0.01', 'inputmode' => 'decimal']]) ?>
        </div>
        <div class="form-grid">
          <?= $this->insert('partials/form/input', ['name' => 'donor_name', 'label' => 'Your Name', 'maxlength' => 150, 'autocomplete' => 'name']) ?>
          <?= $this->insert('partials/form/input', ['name' => 'donor_email', 'label' => 'Email Address', 'type' => 'email', 'maxlength' => 190, 'autocomplete' => 'email', 'hint' => 'Used only to send you a receipt.']) ?>
        </div>
        <?= $this->insert('partials/form/checkbox', ['name' => 'is_anonymous', 'label' => 'I would like my donation to be anonymous.']) ?>
        <?= $this->insert('partials/form/checkbox', [
            'name' => 'privacy', 'required' => true, 'label' => '',
            'labelHtml' => 'I agree to the <a href="' . e(url('/privacy')) . '">Privacy Policy</a> and understand how my information will be used.',
        ]) ?>
        <div class="form__actions">
          <button class="btn" type="submit">Continue to secure payment <?= icon('arrow-right') ?></button>
        </div>
      </form>
    </div>
<?php else: ?>
    <div class="form-card form-card--lg">
      <header class="form-head">
        <span class="form-head__icon"><?= icon('heart-fill') ?></span>
        <div>
          <span class="eyebrow">Donations</span>
          <h2 id="donate-h">Online donations</h2>
        </div>
      </header>
      <div class="notice"><?= icon('info-circle') ?><p><?= e(setting('donations.notice')) ?></p></div>
<?php if ($offline !== ''): ?>
      <h3 class="donate-sub">Other ways to give</h3>
      <div class="prose"><?= format_text($offline) ?></div>
<?php endif; ?>
    </div>

    <div class="support-other">
      <h2>Support in other ways</h2>
      <div class="support-other__grid">
        <a class="support-card" href="<?= e(url('/volunteer')) ?>"><span class="support-card__icon"><?= icon('person-raised-hand') ?></span><span><strong>Volunteer</strong><span>Share your time and skills with our trainees.</span></span><?= icon('chevron-right') ?></a>
        <a class="support-card" href="<?= e(url('/partner')) ?>"><span class="support-card__icon"><?= icon('people') ?></span><span><strong>Partner with us</strong><span>Work with us as an organization or sponsor.</span></span><?= icon('chevron-right') ?></a>
      </div>
    </div>
<?php endif; ?>
  </div>
</section>
