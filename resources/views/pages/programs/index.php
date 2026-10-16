<?php
/** vars: $categories, $programs, $activeCategory, $applicationsOpen */
// Title, description, canonical and share image come from the controller (SEO settings).
$this->layout('layouts/public');
$categories = $categories ?? []; $programs = $programs ?? [];
$active = $activeCategory ?? null;
?>
<?= $this->insert('partials/page-hero', [
    'eyebrow' => (string) setting('programs.hero_eyebrow', 'Our programs'),
    'title' => (string) setting('programs.hero_heading', 'Our Programs'),
    'text' => (string) setting('programs.hero_text', ''),
    'image' => (string) setting('programs.hero_image', ''),
    'imageAlt' => (string) setting('programs.hero_image_alt', ''),
]) ?>

<?php if ($categories): ?>
<div class="cat-strip">
  <nav class="container" aria-label="Program areas">
    <div class="cat-strip__scroll">
      <ul>
        <li class="cat-all-wrap"><a class="cat-all" href="<?= e(url('/programs')) ?>"<?= !$active ? ' aria-current="true"' : '' ?>><?= icon('grid-3x3-gap-fill') ?> All Programs</a></li>
<?php foreach ($categories as $c): $on = $active && ($active['slug'] ?? '') === $c['slug']; ?>
        <li><a href="<?= e(url('/programs', ['category' => $c['slug']])) ?>"<?= $on ? ' aria-current="true"' : '' ?>><?= icon((string) (($c['icon'] ?? '') !== '' ? $c['icon'] : 'lightbulb')) ?><span><?= e($c['name']) ?></span></a></li>
<?php endforeach; ?>
      </ul>
    </div>
  </nav>
</div>
<?php endif; ?>

<section class="section" aria-labelledby="areas-h">
  <div class="container">
    <div class="heading-split">
      <div>
        <span class="eyebrow">Our programs</span>
        <h2 id="areas-h"><?= e($active ? $active['name'] : setting('programs.intro_heading', 'Explore Our Program Areas')) ?></h2>
      </div>
<?php $intro = $active ? (string) ($active['description'] ?? '') : (string) setting('programs.intro_text', ''); if ($intro !== ''): ?>
      <p><?= e($intro) ?></p>
<?php endif; ?>
    </div>

<?php if ($programs): ?>
    <div class="grid-3">
<?php foreach ($programs as $p): $th = (string) ($p['featured_thumb_path'] ?? ''); ?>
      <article class="pcard" id="<?= e('program-' . $p['slug']) ?>">
        <div class="pcard__media">
          <div class="pcard__img<?= $th === '' ? ' ph' : '' ?>"><?php if ($th !== ''): ?><img src="<?= e(upload_url($th)) ?>"<?= srcset($th, '(min-width: 62rem) 360px, (min-width: 40rem) 50vw, 100vw') ?> alt="<?= e((string) ($p['featured_image_alt'] ?? '')) ?>" width="640" height="360" loading="lazy" decoding="async"><?php else: ?><?= icon('image') ?><?php endif; ?></div>
          <span class="icon-badge"><?= icon((string) (($p['icon'] ?? '') !== '' ? $p['icon'] : 'lightbulb')) ?></span>
        </div>
        <div class="pcard__body">
<?php if (!empty($p['applications_open'])): ?><span class="badge badge--primary">Applications open</span><?php endif; ?>
          <h3><a href="<?= e(url('/programs/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
          <p><?= e((string) ($p['summary'] ?? '')) ?></p>
          <span class="link-arrow"><span>Learn More</span> <?= icon('arrow-right') ?></span>
        </div>
      </article>
<?php endforeach; ?>
    </div>
<?php elseif ($active): ?>
    <?= $this->insert('partials/empty-state', ['title' => 'No programs are listed in this area yet.', 'text' => 'Please check back soon, or browse all of our programs.', 'actionLabel' => 'All Programs', 'actionHref' => '/programs']) ?>
<?php elseif ($categories): ?>
    <div class="grid-3">
<?php foreach ($categories as $c): $th = (string) ($c['thumb_path'] ?? ''); ?>
      <article class="pcard" id="<?= e($c['slug']) ?>">
        <div class="pcard__media">
          <div class="pcard__img<?= $th === '' ? ' ph' : '' ?>"><?php if ($th !== ''): ?><img src="<?= e(upload_url($th)) ?>"<?= srcset($th, '(min-width: 62rem) 360px, (min-width: 40rem) 50vw, 100vw') ?> alt="<?= e((string) ($c['image_alt'] ?? '')) ?>" width="640" height="360" loading="lazy" decoding="async"><?php else: ?><?= icon('image') ?><?php endif; ?></div>
          <span class="icon-badge"><?= icon((string) (($c['icon'] ?? '') !== '' ? $c['icon'] : 'lightbulb')) ?></span>
        </div>
        <div class="pcard__body">
          <h3><a href="<?= e(url('/programs', ['category' => $c['slug']])) ?>"><?= e($c['name']) ?></a></h3>
          <p><?= e((string) ($c['description'] ?? '')) ?></p>
          <span class="link-arrow"><span>Learn More</span> <?= icon('arrow-right') ?></span>
        </div>
      </article>
<?php endforeach; ?>
    </div>
<?php else: ?>
    <?= $this->insert('partials/empty-state', ['title' => 'Programs are coming soon.', 'text' => 'We are preparing our program listings. Please check back shortly.']) ?>
<?php endif; ?>
  </div>
</section>

<?= $this->insert('partials/cta-band', [
    'icon' => 'mortarboard-fill',
    'heading' => (string) setting('programs.cta_heading', 'Ready to Build Your Future?'),
    'text' => (string) setting('programs.cta_text', ''),
    'buttonLabel' => 'Apply for Training',
    'buttonHref' => '/apply',
    'image' => (string) setting('programs.cta_image', setting('site.cta_image', '')),
    'imageAlt' => (string) setting('programs.cta_image_alt', setting('site.cta_image_alt', '')),
    'script' => (string) setting('home.hero_script', ''),
]) ?>
