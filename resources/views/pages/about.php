<?php
/** vars: $milestones, $team, $headline (ImpactMetric|null) */
// Title, description, canonical and share image come from the controller (SEO settings).
$this->layout('layouts/public');
$milestones = $milestones ?? []; $team = $team ?? []; $headline = $headline ?? null;
$storyImg = (string) setting('about.story_image', '');
$values = \App\Services\Settings::lines('org.values');
$objectives = \App\Services\Settings::lines('org.objectives');
$beliefs = \App\Services\Settings::lines('org.beliefs');
$legal = trim(setting('org.legal_name') . ((($reg = trim(setting('org.registration_details'))) !== '') ? ' — ' . $reg : ''), ' —');
$initials = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $first = mb_substr((string) ($parts[0] ?? ''), 0, 1);
    $last = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last);
};
?>
<?= $this->insert('partials/page-hero', [
    'breadcrumbs' => [['Home', '/'], ['About Us', null]],
    'eyebrow' => setting('about.hero_eyebrow'), 'title' => setting('about.hero_heading'),
    'tagline' => setting('about.hero_tagline'), 'text' => setting('about.hero_text'),
    'image' => setting('about.hero_image'), 'imageAlt' => setting('about.hero_image_alt'),
]) ?>

<section class="section" aria-labelledby="story-h">
  <div class="container section-split">
    <div>
<?php if (setting('about.story_eyebrow') !== ''): ?><span class="eyebrow"><?= e(setting('about.story_eyebrow')) ?></span><?php endif; ?>
      <h2 id="story-h"><?= e(setting('about.story_heading')) ?></h2>
      <div class="prose"><?= format_text(setting('about.story_text')) ?></div>
    </div>
    <div class="split-photo<?= $storyImg === '' ? ' ph' : '' ?>">
<?php if ($storyImg !== ''): ?>
      <img src="<?= e(upload_url($storyImg)) ?>"<?= srcset($storyImg, '(min-width: 62rem) 30vw, 100vw') ?> alt="<?= e(setting('about.story_image_alt')) ?>" width="640" height="800" loading="lazy" decoding="async">
<?php else: ?><?= icon('image') ?><?php endif; ?>
    </div>
    <div class="info-card">
<?php if (setting('org.mission') !== ''): ?>
      <div class="info-card__item"><span class="info-card__icon"><?= icon('bullseye') ?></span><div><h3>Our Mission</h3><p><?= e(setting('org.mission')) ?></p></div></div>
<?php endif; ?>
<?php if (setting('org.vision') !== ''): ?>
      <div class="info-card__item"><span class="info-card__icon"><?= icon('eye') ?></span><div><h3>Our Vision</h3><p><?= e(setting('org.vision')) ?></p></div></div>
<?php endif; ?>
<?php if ($values): ?>
      <div class="info-card__item"><span class="info-card__icon"><?= icon('star') ?></span><div><h3>Our Core Values</h3>
        <ul class="bullet-list<?= count($values) > 4 ? ' bullet-list--cols' : '' ?>">
<?php foreach ($values as $v): ?>          <li><?= e($v) ?></li>
<?php endforeach; ?>
        </ul></div></div>
<?php endif; ?>
    </div>
  </div>
</section>

<?php if (setting('org.goal') !== '' || $objectives || $beliefs): ?>
<section class="section section--soft" aria-labelledby="goal-h">
  <div class="container split-2">
    <div>
<?php if (setting('org.goal') !== ''): ?>
      <span class="eyebrow">What we aim for</span>
      <h2 id="goal-h">Our Goal</h2>
      <p><?= e(setting('org.goal')) ?></p>
<?php endif; ?>
<?php if ($objectives): ?>
      <h3>Our Objectives</h3>
      <ol class="num-list">
<?php foreach ($objectives as $o): ?>        <li><?= e($o) ?></li>
<?php endforeach; ?>
      </ol>
<?php endif; ?>
    </div>
<?php if ($beliefs): ?>
    <div class="info-card info-card--outline">
      <h3>What We Believe</h3>
      <ul class="feature-list">
<?php foreach ($beliefs as $b): ?>        <li><?= icon('check2') ?><span><?= e($b) ?></span></li>
<?php endforeach; ?>
      </ul>
    </div>
<?php endif; ?>
  </div>
</section>
<?php endif; ?>

<section class="section" aria-labelledby="history-h">
  <div class="container section-split<?= $milestones ? '' : ' section-split--two' ?>">
    <div>
      <span class="eyebrow">Our history</span>
      <h2 id="history-h"><?= e(setting('about.history_heading')) ?></h2>
      <div class="prose"><?= format_text(setting('about.history_text')) ?></div>
<?php if ($legal !== ''): ?>
      <p class="small-print"><?= e($legal) ?></p>
<?php endif; ?>
      <a class="btn" href="<?= e(url('/programs')) ?>">Our Programs <?= icon('arrow-right') ?></a>
    </div>
<?php if ($milestones): ?>
    <ol class="timeline" aria-label="Our journey">
<?php foreach ($milestones as $m): ?>
      <li>
        <span class="timeline__year"><?= e($m['period_label']) ?></span>
        <strong><?= e($m['title']) ?></strong>
<?php if (trim((string) ($m['description'] ?? '')) !== ''): ?>        <p><?= e($m['description']) ?></p>
<?php endif; ?>
      </li>
<?php endforeach; ?>
    </ol>
<?php endif; ?>
<?php if ($headline): ?>
    <div class="highlight-number">
      <span class="eyebrow">Our impact</span>
      <div class="highlight-number__fig"><?= icon('people-fill') ?><span><?= e($headline['value']) ?></span></div>
      <p class="highlight-number__label"><?= e($headline['label']) ?><?php if (!empty($headline['is_approximate'])): ?> <span class="muted">(approximate)</span><?php endif; ?></p>
      <hr>
      <p><?= e(setting('about.impact_text')) ?></p>
    </div>
<?php endif; ?>
  </div>
</section>

<section class="section section--soft" aria-labelledby="team-h">
  <div class="container">
    <div class="section__head">
      <span class="eyebrow">Our team</span>
      <h2 id="team-h"><?= e(setting('about.team_heading')) ?></h2>
<?php if (setting('about.team_text') !== ''): ?><p><?= e(setting('about.team_text')) ?></p><?php endif; ?>
    </div>
<?php if ($team): ?>
    <ul class="team-grid">
<?php foreach ($team as $t): $photo = (string) ($t['photo_thumb_path'] ?: $t['photo_path']); ?>
      <li class="team-card">
<?php if (upload_url($photo) !== ''): ?>
        <img src="<?= e(upload_url($photo)) ?>"<?= srcset($photo, '(min-width: 62rem) 240px, 50vw') ?> alt="<?= e($t['full_name']) ?>" width="320" height="340" loading="lazy" decoding="async">
<?php else: ?>
        <div class="ph" aria-hidden="true"><span class="initials"><?= e($initials((string) $t['full_name'])) ?></span></div>
<?php endif; ?>
        <h3><?= e($t['full_name']) ?></h3>
        <p><strong><?= e($t['role_title']) ?></strong></p>
<?php if (trim((string) ($t['bio'] ?? '')) !== ''): ?>        <p><?= e($t['bio']) ?></p>
<?php endif; ?>
      </li>
<?php endforeach; ?>
    </ul>
<?php else: ?>
    <p class="muted">Our team profiles will be added soon.</p>
<?php endif; ?>
  </div>
</section>

<?= $this->insert('partials/cta-band', [
    'icon' => 'people', 'heading' => 'Join Us in Creating More Opportunities',
    'text' => 'Whether you want to volunteer, partner, or support our work, there is a place for you at RIT Foundation Ghana.',
    'buttonLabel' => 'Get Involved', 'buttonHref' => '/get-involved',
]) ?>
