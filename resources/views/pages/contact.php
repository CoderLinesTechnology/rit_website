<?php
/** vars: $nonce, $mapUrl */
$this->layout('layouts/public');
$phones = array_values(array_filter([trim(setting('contact.phone')), trim(setting('contact.phone_secondary'))]));
$mail = trim(setting('contact.email')); $address = trim(setting('contact.address'));
$postal = trim(setting('contact.postal_address')); $hours = trim(setting('contact.office_hours'));
$mapUrl = (string) ($mapUrl ?? '');
?>
<?= $this->insert('partials/page-hero', [
    'breadcrumbs' => [['Home', '/'], ['Contact Us', null]],
    'eyebrow' => (string) setting('contact.hero_eyebrow', ''),
    'title' => (string) setting('contact.hero_heading', 'Contact Us'),
    'tagline' => (string) setting('contact.hero_tagline', ''),
    'text' => (string) setting('contact.hero_text', ''),
    'image' => (string) setting('contact.hero_image', ''),
    'imageAlt' => (string) setting('contact.hero_image_alt', ''),
]) ?>

<section class="section" aria-labelledby="contact-h">
  <div class="container form-layout form-layout--contact">
    <div class="contact-details">
      <h2 id="contact-h">Get in touch</h2>
<?php if ($phones): ?>
      <div class="contact-card"><span class="contact-card__icon"><?= icon('telephone') ?></span><div><h3>Telephone</h3>
<?php foreach ($phones as $ph): ?>
        <p><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $ph)) ?>"><?= e($ph) ?></a></p>
<?php endforeach; ?>
      </div></div>
<?php endif; ?>
<?php if ($mail !== ''): ?>
      <div class="contact-card"><span class="contact-card__icon"><?= icon('envelope') ?></span><div><h3>Email</h3><p><a href="<?= e(mailto_url($mail)) ?>"><?= e($mail) ?></a></p></div></div>
<?php endif; ?>
<?php if ($address !== '' || $postal !== ''): ?>
      <div class="contact-card"><span class="contact-card__icon"><?= icon('geo-alt') ?></span><div><h3>Address</h3>
<?php if ($address !== ''): ?><p><?= nl2br(e($address)) ?></p><?php endif; ?>
<?php if ($postal !== ''): ?><p><span class="muted">Postal:</span> <?= e($postal) ?></p><?php endif; ?>
      </div></div>
<?php endif; ?>
<?php if ($hours !== ''): ?>
      <div class="contact-card"><span class="contact-card__icon"><?= icon('clock-history') ?></span><div><h3>Office hours</h3><p><?= e($hours) ?></p></div></div>
<?php endif; ?>
<?php if (social_links()): ?>
      <div class="contact-card contact-card--social"><div><h3>Follow us</h3><?= $this->insert('partials/social-links', ['variant' => 'inline']) ?></div></div>
<?php endif; ?>
<?php if ($mapUrl !== ''): ?>
      <div class="map-embed"><iframe title="Map showing our location" src="<?= e($mapUrl) ?>" loading="lazy" referrerpolicy="no-referrer" width="600" height="320"></iframe></div>
<?php endif; ?>
    </div>

    <div class="form-card form-card--lg">
      <header class="form-head">
        <span class="form-head__icon"><?= icon('envelope') ?></span>
        <div>
          <span class="eyebrow">Send a message</span>
          <h2>How can we help?</h2>
          <p>Fields marked * are required.</p>
        </div>
      </header>
      <?= $this->insert('partials/error-summary') ?>
      <form class="form" method="post" action="<?= e(url('/contact')) ?>" data-once data-busy-text="Sending…">
        <?= csrf_field() ?>
        <?= \App\Services\FormGuard::fields($nonce) ?>
        <div class="form-grid">
          <?= $this->insert('partials/form/input', ['name' => 'full_name', 'label' => 'Full Name', 'required' => true, 'maxlength' => 150, 'autocomplete' => 'name']) ?>
          <?= $this->insert('partials/form/input', ['name' => 'email', 'label' => 'Email Address', 'type' => 'email', 'required' => true, 'maxlength' => 190, 'autocomplete' => 'email']) ?>
          <?= $this->insert('partials/form/input', ['name' => 'phone', 'label' => 'Telephone Number', 'type' => 'tel', 'maxlength' => 30, 'autocomplete' => 'tel', 'attrs' => ['inputmode' => 'tel']]) ?>
          <?= $this->insert('partials/form/input', ['name' => 'subject', 'label' => 'Subject', 'required' => true, 'maxlength' => 190]) ?>
          <div class="form-grid__full"><?= $this->insert('partials/form/textarea', ['name' => 'message', 'label' => 'Message', 'required' => true, 'rows' => 6, 'maxlength' => 3000, 'minlength' => 10, 'counter' => true]) ?></div>
          <div class="form-grid__full"><?= $this->insert('partials/form/checkbox', [
              'name' => 'privacy', 'required' => true, 'label' => '',
              'labelHtml' => 'I agree to the <a href="' . e(url('/privacy')) . '">Privacy Policy</a> and understand how my information will be used.',
          ]) ?></div>
        </div>
        <div class="form__actions">
          <button class="btn" type="submit">Send Message <?= icon('arrow-right') ?></button>
        </div>
      </form>
    </div>
  </div>
</section>
