<?php
/** vars: $metrics, $story (consented success story or null) */
// Title, description, canonical and share image come from the controller (SEO settings).
$this->layout('layouts/public');
$metrics = $metrics ?? []; $story = $story ?? null;
$storyImg = (string) setting('impact.story_image', '');
$storiesImg = (string) setting('impact.stories_image', '');
$areaIcons = ['mortarboard', 'graph-up-arrow', 'house-heart', 'people'];
$areas = [];
foreach (\App\Services\Settings::lines('impact.areas') as $line) {
    [$t, $d] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
    if ($t !== '') { $areas[] = [$t, $d]; }
}
$quote = $story !== null ? trim((string) ($story['excerpt'] ?? '')) : '';
?>
<?= $this->insert('partials/page-hero', [
    'breadcrumbs' => [['Home', '/'], ['Our Impact', null]],
    'eyebrow' => setting('impact.hero_eyebrow'), 'title' => setting('impact.hero_heading'),
    'tagline' => setting('impact.hero_tagline'), 'text' => setting('impact.hero_text'),
    'image' => setting('impact.hero_image'), 'imageAlt' => setting('impact.hero_image_alt'),
]) ?>

<?php if ($metrics): ?>
<section class="section section--tight" aria-label="Impact in numbers">
  <div class="container">
    <ul class="stat-row">
<?php foreach ($metrics as $m): ?>
      <li class="stat-card">
        <?= icon((string) (($m['icon'] ?? '') !== '' ? $m['icon'] : 'people-fill')) ?>
        <span class="stat-card__value"><?php if (!empty($m['is_approximate'])): ?><span class="visually-hidden">Approximately </span><?php endif; ?><?= e($m['value']) ?></span>
        <span class="stat-card__label"><?= e($m['label']) ?></span>
<?php if (trim((string) ($m['description'] ?? '')) !== ''): ?>        <span class="stat-card__desc"><?= e($m['description']) ?></span>
<?php endif; ?>
<?php if (trim((string) ($m['reporting_period'] ?? '')) !== '' || !empty($m['is_approximate'])): ?>
        <span class="stat-card__desc"><?= e(trim((string) ($m['reporting_period'] ?? ''))) ?><?php if (!empty($m['is_approximate'])): ?><?= trim((string) ($m['reporting_period'] ?? '')) !== '' ? ' · ' : '' ?>Approximate figure<?php endif; ?></span>
<?php endif; ?>
      </li>
<?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<section class="section" aria-labelledby="impact-story-h">
  <div class="container section-split">
    <div>
<?php if (setting('impact.story_eyebrow') !== ''): ?><span class="eyebrow"><?= e(setting('impact.story_eyebrow')) ?></span><?php endif; ?>
      <h2 id="impact-story-h"><?= e(setting('impact.story_heading')) ?></h2>
      <div class="prose"><?= format_text(setting('impact.story_text')) ?></div>
      <a class="btn" href="<?= e(url('/programs')) ?>">Our Programs <?= icon('arrow-right') ?></a>
    </div>
    <div class="split-photo<?= $storyImg === '' ? ' ph' : '' ?>">
<?php if ($storyImg !== ''): ?>
      <img src="<?= e(upload_url($storyImg)) ?>"<?= srcset($storyImg, '(min-width: 62rem) 30vw, 100vw') ?> alt="<?= e(setting('impact.story_image_alt')) ?>" width="640" height="800" loading="lazy" decoding="async">
<?php else: ?><?= icon('image') ?><?php endif; ?>
    </div>
<?php if ($areas): ?>
    <div class="info-card">
      <h3>Key Areas of Impact</h3>
<?php foreach ($areas as $i => [$t, $d]): ?>
      <div class="info-card__item"><span class="info-card__icon"><?= icon($areaIcons[$i % 4]) ?></span><div><h4><?= e($t) ?></h4><?php if ($d !== ''): ?><p><?= e($d) ?></p><?php endif; ?></div></div>
<?php endforeach; ?>
    </div>
<?php endif; ?>
  </div>
</section>

<section class="section section--soft" aria-labelledby="stories-h">
  <div class="container section-split<?= $quote === '' ? ' section-split--two' : '' ?>">
    <div class="split-photo split-photo--wide<?= $storiesImg === '' ? ' ph' : '' ?>">
<?php if ($storiesImg !== ''): ?>
      <img src="<?= e(upload_url($storiesImg)) ?>"<?= srcset($storiesImg, '(min-width: 62rem) 33vw, 100vw') ?> alt="<?= e(setting('impact.stories_image_alt')) ?>" width="640" height="480" loading="lazy" decoding="async">
<?php else: ?><?= icon('image') ?><?php endif; ?>
<?php if (setting('home.hero_script') !== ''): ?>
      <p class="photo-script" aria-hidden="true"><?= e(setting('home.hero_script')) ?></p>
<?php endif; ?>
    </div>
    <div class="soft-card">
      <span class="eyebrow">Real stories</span>
      <h2 id="stories-h"><?= e(setting('impact.stories_heading')) ?></h2>
      <p><?= e(setting('impact.stories_text')) ?></p>
      <a class="btn" href="<?= e(url('/news/category/success-stories')) ?>">Read Success Stories <?= icon('arrow-right') ?></a>
    </div>
<?php if ($quote !== ''): ?>
    <figure class="quote-card">
      <?= icon('quote') ?>
      <blockquote><p><?= e($quote) ?></p></blockquote>
      <figcaption>&mdash; <a href="<?= e(url('/news/' . $story['slug'])) ?>"><?= e($story['title']) ?></a></figcaption>
    </figure>
<?php endif; ?>
  </div>
</section>

<?= $this->insert('partials/cta-band', [
    'icon' => 'people', 'eyebrow' => 'Be part of our impact', 'heading' => 'Together, We Can Do More',
    'text' => 'Support our mission by volunteering, partnering or donating. Your contribution helps create real and lasting change.',
    'buttonLabel' => 'Get Involved', 'buttonHref' => '/get-involved', 'script' => 'Real Skills Real Change',
]) ?>
