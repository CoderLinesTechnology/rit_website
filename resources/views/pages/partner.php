<?php
/** vars: $nonce */
$this->layout('layouts/public');
$types = \App\Models\PartnershipEnquiry::TYPES;
$ways = \App\Services\Settings::lines('get-involved.partner_ways');
$phone = trim(setting('contact.phone')); $mail = trim(setting('contact.email'));
?>
<?= $this->insert('partials/page-hero', [
    'breadcrumbs' => [['Home', '/'], ['Get Involved', '/get-involved'], ['Partner', null]],
    'eyebrow' => (string) setting('partner.hero_eyebrow', ''),
    'title' => (string) setting('partner.hero_heading', 'Partner With Us'),
    'tagline' => (string) setting('partner.hero_tagline', ''),
    'text' => (string) setting('partner.hero_text', ''),
    'image' => (string) setting('partner.hero_image', ''),
    'imageAlt' => (string) setting('partner.hero_image_alt', ''),
]) ?>

<section class="section" aria-labelledby="partner-h">
  <div class="container form-layout">
    <div class="form-card form-card--lg">
      <header class="form-head">
        <span class="form-head__icon"><?= icon('people') ?></span>
        <div>
          <span class="eyebrow">Partnership enquiry</span>
          <h2 id="partner-h">Work with us</h2>
          <p>Tell us about your organization and how you would like to work together. Fields marked * are required.</p>
        </div>
      </header>
      <?= $this->insert('partials/error-summary') ?>
      <form class="form" method="post" action="<?= e(url('/partner')) ?>" data-once data-busy-text="Sending…">
        <?= csrf_field() ?>
        <?= \App\Services\FormGuard::fields($nonce) ?>
        <div class="form-grid">
          <div class="form-grid__full"><?= $this->insert('partials/form/input', ['name' => 'organization_name', 'label' => 'Organization Name', 'required' => true, 'maxlength' => 190, 'autocomplete' => 'organization']) ?></div>
          <?= $this->insert('partials/form/input', ['name' => 'contact_name', 'label' => 'Contact Person', 'required' => true, 'maxlength' => 150, 'autocomplete' => 'name']) ?>
          <?= $this->insert('partials/form/input', ['name' => 'email', 'label' => 'Email Address', 'type' => 'email', 'required' => true, 'maxlength' => 190, 'autocomplete' => 'email']) ?>
          <?= $this->insert('partials/form/input', ['name' => 'phone', 'label' => 'Telephone Number', 'type' => 'tel', 'maxlength' => 30, 'autocomplete' => 'tel', 'attrs' => ['inputmode' => 'tel']]) ?>
          <?= $this->insert('partials/form/input', ['name' => 'website', 'label' => 'Website', 'type' => 'url', 'maxlength' => 255, 'autocomplete' => 'url', 'attrs' => ['placeholder' => 'https://']]) ?>
          <div class="form-grid__full"><?= $this->insert('partials/form/select', ['name' => 'partnership_type', 'label' => 'Type of Partnership', 'required' => true, 'options' => $types, 'placeholder' => 'Select a type of partnership']) ?></div>
          <div class="form-grid__full"><?= $this->insert('partials/form/textarea', ['name' => 'message', 'label' => 'Message', 'required' => true, 'rows' => 5, 'maxlength' => 3000, 'minlength' => 20, 'counter' => true, 'hint' => 'At least 20 characters.']) ?></div>
          <div class="form-grid__full"><?= $this->insert('partials/form/checkbox', [
              'name' => 'privacy', 'required' => true, 'label' => '',
              'labelHtml' => 'I agree to the <a href="' . e(url('/privacy')) . '">Privacy Policy</a> and understand how my information will be used.',
          ]) ?></div>
        </div>
        <div class="form__actions">
          <button class="btn" type="submit">Send Enquiry <?= icon('arrow-right') ?></button>
        </div>
      </form>
    </div>

    <aside class="form-aside" aria-label="About partnerships">
<?php if ($ways): ?>
      <div class="sidebar-card">
        <h3>Ways to partner</h3>
        <ul class="feature-list">
<?php foreach ($ways as $line): ?>
          <li><?= icon('check-circle') ?><span><?= e($line) ?></span></li>
<?php endforeach; ?>
        </ul>
      </div>
<?php endif; ?>
      <div class="sidebar-card sidebar-card--pink">
        <h3>Need Help?</h3>
        <p>If you would like to talk before sending an enquiry, feel free to contact us.</p>
        <ul class="plain-list contact-list">
<?php if ($phone !== ''): ?><li><?= icon('telephone') ?><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $phone)) ?>"><?= e($phone) ?></a></li><?php endif; ?>
<?php if ($mail !== ''): ?><li><?= icon('envelope') ?><a href="<?= e(mailto_url($mail)) ?>"><?= e($mail) ?></a></li><?php endif; ?>
        </ul>
      </div>
    </aside>
  </div>
</section>
