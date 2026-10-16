<?php
/** vars: none (all text from settings) */
// Title, description, canonical and share image come from the controller (SEO settings).
$this->layout('layouts/public');
use App\Services\Settings;
$photo = static function (string $key, string $w, string $h): string {
    $img = (string) setting($key, '');
    if ($img === '') { return '<div class="split-photo ph">' . icon('image') . '</div>'; }
    return '<div class="split-photo"><img src="' . e(upload_url($img)) . '"' . srcset($img, '(min-width: 62rem) 30vw, 100vw') . ' alt="' . e(setting($key . '_alt')) . '" width="' . $w . '" height="' . $h . '" loading="lazy" decoding="async"></div>';
};
$checks = static function (array $items): string {
    $out = '<ul class="feature-list">';
    foreach ($items as $i) { $out .= '<li>' . icon('check2') . '<span>' . e($i) . '</span></li>'; }
    return $out . '</ul>';
};
$why = Settings::lines('get-involved.volunteer_why');
$quote = trim(setting('get-involved.volunteer_quote'));
?>
<?= $this->insert('partials/page-hero', [
    'breadcrumbs' => [['Home', '/'], ['Get Involved', null]],
    'eyebrow' => setting('get-involved.hero_eyebrow'), 'title' => setting('get-involved.hero_heading'),
    'tagline' => setting('get-involved.hero_tagline'), 'text' => setting('get-involved.hero_text'),
    'image' => setting('get-involved.hero_image'), 'imageAlt' => setting('get-involved.hero_image_alt'),
]) ?>

<section class="section" aria-labelledby="volunteer-h">
  <div class="container section-split">
    <?= $photo('get-involved.volunteer_image', '640', '800') ?>
    <div>
      <span class="icon-circle"><?= icon('people') ?></span>
      <span class="eyebrow">Volunteer</span>
      <h2 id="volunteer-h"><?= e(setting('get-involved.volunteer_heading')) ?></h2>
      <p><?= e(setting('get-involved.volunteer_text')) ?></p>
<?php if (Settings::lines('get-involved.volunteer_ways')): ?>
      <p><strong>Ways to volunteer:</strong></p>
      <?= $checks(Settings::lines('get-involved.volunteer_ways')) ?>
<?php endif; ?>
      <p class="spaced"><a class="btn" href="<?= e(url('/volunteer')) ?>">Apply to Volunteer <?= icon('arrow-right') ?></a></p>
    </div>
<?php if ($why || $quote !== ''): ?>
    <div class="soft-card">
      <span class="icon-circle icon-circle--plain"><?= icon('balloon-heart') ?></span>
      <h3>Why Volunteer?</h3>
<?php if ($why): ?>
      <ul class="bullet-list">
<?php foreach ($why as $w): ?>        <li><?= e($w) ?></li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
<?php if ($quote !== ''): ?>
      <p class="quote-line"><em><?= e($quote) ?></em></p>
<?php endif; ?>
    </div>
<?php endif; ?>
  </div>
</section>

<section class="section section--soft" aria-labelledby="partner-h">
  <div class="container">
    <div class="wide-card">
      <div>
        <span class="icon-circle"><?= icon('people') ?></span>
        <span class="eyebrow">Partnerships</span>
        <h2 id="partner-h"><?= e(setting('get-involved.partner_heading')) ?></h2>
        <p><?= e(setting('get-involved.partner_text')) ?></p>
        <a class="btn btn--secondary" href="<?= e(url('/partner')) ?>">Become a Partner <?= icon('arrow-right') ?></a>
      </div>
<?php if (Settings::lines('get-involved.partner_ways')): ?>
      <div class="wide-card__list">
        <p><strong>Partnership opportunities include:</strong></p>
        <?= $checks(Settings::lines('get-involved.partner_ways')) ?>
      </div>
<?php endif; ?>
      <?= $photo('get-involved.partner_image', '640', '480') ?>
    </div>
  </div>
</section>

<section class="section" aria-labelledby="support-h">
  <div class="container section-split">
    <?= $photo('get-involved.support_image', '640', '560') ?>
    <div>
      <span class="icon-circle"><?= icon('heart') ?></span>
      <span class="eyebrow">Support</span>
      <h2 id="support-h"><?= e(setting('get-involved.support_heading')) ?></h2>
      <p><?= e(setting('get-involved.support_text')) ?></p>
      <a class="btn btn--secondary" href="<?= e(url('/donate')) ?>">Support Our Work <?= icon('arrow-right') ?></a>
    </div>
<?php if (Settings::lines('get-involved.support_ways')): ?>
    <div class="soft-card">
      <span class="icon-circle icon-circle--plain"><?= icon('heart') ?></span>
      <h3>Ways to Support</h3>
      <?= $checks(Settings::lines('get-involved.support_ways')) ?>
    </div>
<?php endif; ?>
  </div>
</section>

<?= $this->insert('partials/cta-band', [
    'icon' => 'people', 'eyebrow' => 'Get involved today', 'heading' => 'Together, We Create More Opportunities',
    'text' => 'Volunteer. Partner. Support. Be part of the change.',
    'buttonLabel' => 'Contact Us', 'buttonHref' => '/contact',
]) ?>
