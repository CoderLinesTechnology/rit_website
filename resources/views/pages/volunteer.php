<?php
/** vars: $nonce */
$this->layout('layouts/public');
$areas = \App\Models\VolunteerApplication::INTEREST_AREAS;
$regions = array_combine(\App\Models\TrainingApplication::REGIONS, \App\Models\TrainingApplication::REGIONS);
$why = \App\Services\Settings::lines('get-involved.volunteer_why');
$phone = trim(setting('contact.phone')); $mail = trim(setting('contact.email'));
?>
<?= $this->insert('partials/page-hero', [
    'breadcrumbs' => [['Home', '/'], ['Get Involved', '/get-involved'], ['Volunteer', null]],
    'eyebrow' => (string) setting('volunteer.hero_eyebrow', ''),
    'title' => (string) setting('volunteer.hero_heading', 'Volunteer With Us'),
    'tagline' => (string) setting('volunteer.hero_tagline', ''),
    'text' => (string) setting('volunteer.hero_text', ''),
    'image' => (string) setting('volunteer.hero_image', ''),
    'imageAlt' => (string) setting('volunteer.hero_image_alt', ''),
]) ?>

<section class="section" aria-labelledby="volunteer-h">
  <div class="container form-layout">
    <div class="form-card form-card--lg">
      <header class="form-head">
        <span class="form-head__icon"><?= icon('person-raised-hand') ?></span>
        <div>
          <span class="eyebrow">Volunteer application</span>
          <h2 id="volunteer-h">Offer your time and skills</h2>
          <p>Tell us a little about yourself and how you would like to help. Fields marked * are required.</p>
        </div>
      </header>
      <?= $this->insert('partials/error-summary') ?>
      <form class="form" method="post" action="<?= e(url('/volunteer')) ?>" data-once data-busy-text="Sending…">
        <?= csrf_field() ?>
        <?= \App\Services\FormGuard::fields($nonce) ?>
        <div class="form-grid">
          <?= $this->insert('partials/form/input', ['name' => 'full_name', 'label' => 'Full Name', 'required' => true, 'maxlength' => 150, 'autocomplete' => 'name']) ?>
          <?= $this->insert('partials/form/input', ['name' => 'email', 'label' => 'Email Address', 'type' => 'email', 'required' => true, 'maxlength' => 190, 'autocomplete' => 'email']) ?>
          <?= $this->insert('partials/form/input', ['name' => 'phone', 'label' => 'Telephone Number', 'type' => 'tel', 'maxlength' => 30, 'autocomplete' => 'tel', 'attrs' => ['inputmode' => 'tel']]) ?>
          <?= $this->insert('partials/form/select', ['name' => 'location', 'label' => 'Region', 'options' => $regions, 'placeholder' => 'Select your region']) ?>
          <div class="form-grid__full"><?= $this->insert('partials/form/select', ['name' => 'interest_area', 'label' => 'How would you like to help?', 'required' => true, 'options' => $areas, 'placeholder' => 'Select an area of interest']) ?></div>
          <div class="form-grid__full"><?= $this->insert('partials/form/textarea', ['name' => 'skills', 'label' => 'Skills and Experience', 'rows' => 4, 'maxlength' => 2000, 'counter' => true]) ?></div>
          <div class="form-grid__full"><?= $this->insert('partials/form/input', ['name' => 'availability', 'label' => 'Availability', 'maxlength' => 255, 'attrs' => ['placeholder' => 'e.g. Saturdays, or two evenings a week']]) ?></div>
          <div class="form-grid__full"><?= $this->insert('partials/form/textarea', ['name' => 'message', 'label' => 'Anything else you would like us to know', 'rows' => 4, 'maxlength' => 2000, 'counter' => true]) ?></div>
          <div class="form-grid__full"><?= $this->insert('partials/form/checkbox', [
              'name' => 'privacy', 'required' => true, 'label' => '',
              'labelHtml' => 'I agree to the <a href="' . e(url('/privacy')) . '">Privacy Policy</a> and understand how my information will be used.',
          ]) ?></div>
        </div>
        <div class="form__actions">
          <button class="btn" type="submit">Send Application <?= icon('arrow-right') ?></button>
        </div>
      </form>
    </div>

    <aside class="form-aside" aria-label="About volunteering">
<?php if ($why): ?>
      <div class="sidebar-card">
        <h3>Why volunteer?</h3>
        <ul class="feature-list">
<?php foreach ($why as $line): ?>
          <li><?= icon('check-circle') ?><span><?= e($line) ?></span></li>
<?php endforeach; ?>
        </ul>
      </div>
<?php endif; ?>
      <div class="sidebar-card sidebar-card--pink">
        <h3>Need Help?</h3>
        <p>If you have a question before you apply, feel free to contact us.</p>
        <ul class="plain-list contact-list">
<?php if ($phone !== ''): ?><li><?= icon('telephone') ?><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $phone)) ?>"><?= e($phone) ?></a></li><?php endif; ?>
<?php if ($mail !== ''): ?><li><?= icon('envelope') ?><a href="<?= e(mailto_url($mail)) ?>"><?= e($mail) ?></a></li><?php endif; ?>
        </ul>
      </div>
    </aside>
  </div>
</section>
