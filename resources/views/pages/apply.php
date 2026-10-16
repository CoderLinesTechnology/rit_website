<?php
/** vars: $nonce, $programs, $preselect, $allowAttachment, $categories */
$this->layout('layouts/public');
$programs = $programs ?? []; $categories = $categories ?? [];
$allowAttachment = !empty($allowAttachment);
$ages = []; foreach (\App\Models\TrainingApplication::AGE_RANGES as $k => $a) { $ages[$k] = $a['label']; }
$regions = array_combine(\App\Models\TrainingApplication::REGIONS, \App\Models\TrainingApplication::REGIONS);
$programOptions = []; foreach ($programs as $p) { $programOptions[(string) $p['id']] = (string) $p['title']; }
$phone = trim(setting('contact.phone')); $mail = trim(setting('contact.email')); $address = trim(setting('contact.address'));
?>
<?= $this->insert('partials/page-hero', [
    'breadcrumbs' => [['Home', '/'], [(string) setting('apply.hero_heading', 'Training Applications'), null]],
    'eyebrow' => (string) setting('apply.hero_eyebrow', ''),
    'title' => (string) setting('apply.hero_heading', 'Training Applications'),
    'tagline' => (string) setting('apply.hero_tagline', ''),
    'text' => (string) setting('apply.hero_text', ''),
    'image' => (string) setting('apply.hero_image', ''),
    'imageAlt' => (string) setting('apply.hero_image_alt', ''),
]) ?>

<section class="section" aria-labelledby="apply-h">
  <div class="container form-layout">
    <div class="form-card form-card--lg">
      <header class="form-head">
        <span class="form-head__icon"><?= icon('file-earmark-text') ?></span>
        <div>
          <span class="eyebrow">Training application</span>
          <h2 id="apply-h">Apply for Training</h2>
          <p><?= e(setting('apply.intro_text')) ?></p>
        </div>
      </header>

<?php if (!$programs): ?>
      <div class="closed-panel">
        <?= icon("info-circle") ?>
        <p><?= e(setting('applications.closed_message')) ?></p>
        <p><a class="btn" href="<?= e(url('/programs')) ?>">View our programs <?= icon('arrow-right') ?></a></p>
        <?= $this->insert('partials/social-links', ['variant' => 'inline']) ?>
      </div>
<?php else: ?>
<?php if (setting('apply.notice') !== ''): ?>
      <div class="notice"><?= icon('info-circle') ?><p><?= e(setting('apply.notice')) ?></p></div>
<?php endif; ?>
      <?= $this->insert('partials/error-summary') ?>

      <form class="form" method="post" action="<?= e(url('/apply')) ?>" data-once data-busy-text="Sending…"<?= $allowAttachment ? ' enctype="multipart/form-data"' : '' ?>>
        <?= csrf_field() ?>
        <?= \App\Services\FormGuard::fields($nonce) ?>
        <div class="form-grid">
          <?= $this->insert('partials/form/input', ['name' => 'full_name', 'label' => 'Full Name', 'required' => true, 'maxlength' => 150, 'autocomplete' => 'name']) ?>
          <?= $this->insert('partials/form/input', ['name' => 'email', 'label' => 'Email Address', 'type' => 'email', 'required' => true, 'maxlength' => 190, 'autocomplete' => 'email']) ?>
          <?= $this->insert('partials/form/input', ['name' => 'phone', 'label' => 'Telephone Number', 'type' => 'tel', 'required' => true, 'maxlength' => 30, 'autocomplete' => 'tel', 'attrs' => ['placeholder' => 'e.g. 024 123 4567', 'inputmode' => 'tel']]) ?>
          <?= $this->insert('partials/form/select', ['name' => 'age_range', 'label' => 'Age', 'required' => true, 'options' => $ages, 'placeholder' => 'Select your age range']) ?>
          <?= $this->insert('partials/form/select', ['name' => 'location', 'label' => 'Region', 'required' => true, 'options' => $regions, 'placeholder' => 'Select your region', 'attrs' => ['autocomplete' => 'address-level1']]) ?>
          <?= $this->insert('partials/form/select', ['name' => 'program_id', 'label' => 'Preferred Program', 'required' => true, 'options' => $programOptions, 'value' => old('program_id', (string) ($preselect ?? '')), 'placeholder' => 'Select a program']) ?>

          <fieldset class="form-block form-grid__full" data-show-when="age_range=under-15|15-17">
            <legend>Parent or guardian <span>(required if you are under 18)</span></legend>
            <div class="form-grid">
              <?php foreach ([['guardian_name', 'Parent or Guardian Name', 'text', 150, 'name'], ['guardian_phone', 'Parent or Guardian Telephone', 'tel', 30, 'tel']] as [$gn, $gl, $gt, $gm, $ga]):
                  $gerr = field_error($gn); ?>
              <div class="field<?= $gerr ? ' field--invalid' : '' ?>">
                <label class="field__label" for="field-<?= e($gn) ?>"><?= e($gl) ?> <span class="field__req" aria-hidden="true">*</span><span class="visually-hidden">(required if you are under 18)</span></label>
                <input class="input" type="<?= e($gt) ?>" id="field-<?= e($gn) ?>" name="<?= e($gn) ?>" value="<?= e(old($gn)) ?>" maxlength="<?= (int) $gm ?>" autocomplete="<?= e($ga) ?>"<?= $gerr ? ' aria-invalid="true" aria-describedby="field-' . e($gn) . '-error"' : '' ?>>
<?php if ($gerr): ?>
                <p class="field__error" id="field-<?= e($gn) ?>-error"><?= e($gerr) ?></p>
<?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
            <?= $this->insert('partials/form/checkbox', [
                'name' => 'guardian_consent',
                'label' => 'I am the applicant’s parent or guardian and I consent to this application and to RIT Foundation Ghana contacting me about it.',
            ]) ?>
          </fieldset>

          <div class="form-grid__full"><?= $this->insert('partials/form/textarea', ['name' => 'background', 'label' => 'Educational Background / Relevant Experience', 'rows' => 4, 'maxlength' => 2000, 'attrs' => ['placeholder' => 'e.g. SHS, JHS, or other relevant skills']]) ?></div>
          <div class="form-grid__full"><?= $this->insert('partials/form/textarea', ['name' => 'reason', 'label' => 'Reason for Applying', 'required' => true, 'rows' => 5, 'maxlength' => 2000, 'minlength' => 20, 'counter' => true, 'hint' => 'Tell us why you would like to join (at least 20 characters).']) ?></div>
          <?= $this->insert('partials/form/select', ['name' => 'availability', 'label' => 'Availability', 'required' => true, 'options' => \App\Models\TrainingApplication::AVAILABILITY, 'placeholder' => 'Select your availability']) ?>

<?php if ($allowAttachment): $aerr = field_error('attachment'); ?>
          <div class="field<?= $aerr ? ' field--invalid' : '' ?>">
            <label class="field__label" for="field-attachment">Supporting Document <span class="field__opt">(Optional)</span></label>
            <p class="field__hint" id="field-attachment-hint">Accepted formats: PDF, JPG, PNG (max. 5 MB)</p>
            <input type="file" id="field-attachment" name="attachment" accept=".pdf,.jpg,.jpeg,.png" aria-describedby="field-attachment-hint<?= $aerr ? ' field-attachment-error' : '' ?>"<?= $aerr ? ' aria-invalid="true"' : '' ?>>
<?php if ($aerr): ?>
            <p class="field__error" id="field-attachment-error"><?= e($aerr) ?></p>
<?php endif; ?>
          </div>
<?php endif; ?>

          <div class="form-grid__full"><?= $this->insert('partials/form/checkbox', [
              'name' => 'privacy', 'required' => true, 'label' => '',
              'labelHtml' => 'I agree to the <a href="' . e(url('/privacy')) . '">Privacy Policy</a> and understand how my information will be used.',
          ]) ?></div>
        </div>
        <div class="form__actions">
          <button class="btn" type="submit">Submit Application <?= icon('arrow-right') ?></button>
        </div>
      </form>
<?php endif; ?>
    </div>

    <aside class="form-aside" aria-label="About our programs">
      <div class="sidebar-card side-card">
        <div class="side-card__head">
          <span class="side-card__icon"><?= icon('mortarboard-fill') ?></span>
          <h3>Our Training Programs</h3>
        </div>
        <p><?= e(setting('apply.sidebar_text')) ?></p>
        <a class="btn btn--sm" href="<?= e(url('/programs')) ?>">View All Programs <?= icon('arrow-right') ?></a>
      </div>

<?php if ($categories): ?>
      <div class="sidebar-card">
        <h3>Popular Programs</h3>
        <ul class="plain-list">
<?php foreach ($categories as $c): ?>
          <li class="list-row list-row--icon">
            <span class="list-row__icon"><?= icon((string) (($c['icon'] ?? '') !== '' ? $c['icon'] : 'lightbulb')) ?></span>
            <a href="<?= e(url('/programs', ['category' => $c['slug']])) ?>"><?= e($c['name']) ?></a>
            <?= icon('chevron-right') ?>
          </li>
<?php endforeach; ?>
        </ul>
      </div>
<?php endif; ?>

      <div class="sidebar-card sidebar-card--pink">
        <h3>Need Help?</h3>
        <p>If you have any questions about the application process or our programs, feel free to contact us.</p>
        <ul class="plain-list contact-list">
<?php if ($phone !== ''): ?><li><?= icon('telephone') ?><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $phone)) ?>"><?= e($phone) ?></a></li><?php endif; ?>
<?php if ($mail !== ''): ?><li><?= icon('envelope') ?><a href="<?= e(mailto_url($mail)) ?>"><?= e($mail) ?></a></li><?php endif; ?>
<?php if ($address !== ''): ?><li><?= icon('geo-alt') ?><span><?= e($address) ?></span></li><?php endif; ?>
        </ul>
      </div>
    </aside>
  </div>
</section>

<section class="section section--soft" aria-labelledby="process-h">
  <div class="container">
    <div class="steps-panel">
      <h2 id="process-h">Application Process</h2>
      <ol class="steps">
        <li><strong>Complete the Form</strong><p>Fill in all required details and submit your application.</p></li>
        <li><strong>Receive Your Reference</strong><p>You will see your application reference number straight away, and receive it by email when email is available.</p></li>
        <li><strong>Next Steps</strong><p>If selected, you will receive further instructions about your training program.</p></li>
      </ol>
    </div>
  </div>
</section>

<?= $this->insert('partials/cta-band', [
    'icon' => 'people',
    'heading' => 'Be Part of Our Story',
    'text' => 'Support our mission by volunteering, partnering or making a donation. Together, we can create more opportunities for young people in Ghana.',
    'buttonLabel' => 'Get Involved',
    'buttonHref' => '/get-involved',
    'script' => 'Skills Build Futures',
]) ?>
