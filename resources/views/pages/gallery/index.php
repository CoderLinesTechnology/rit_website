<?php
/** vars: $albums (published), $album (current or null), $images, $paginator */
// Title, description, canonical and share image come from the controller (SEO settings).
$this->layout('layouts/public');
$albums = $albums ?? []; $album = $album ?? null; $images = $images ?? [];
$crumbs = $album ? [['Home', '/'], ['Gallery', '/gallery'], [(string) $album['name'], null]] : [['Home', '/'], ['Gallery', null]];
$pills = [['All', url('/gallery'), $album === null]];
foreach ($albums as $a) { $pills[] = [(string) $a['name'], url('/gallery/' . $a['slug']), $album !== null && $album['slug'] === $a['slug']]; }
$external = $album && preg_match('#^https?://#i', (string) ($album['external_url'] ?? '')) ? (string) $album['external_url'] : '';
?>
<?= $this->insert('partials/page-hero', [
    'breadcrumbs' => $crumbs,
    'eyebrow' => setting('gallery.hero_eyebrow'), 'title' => setting('gallery.hero_heading'),
    'text' => setting('gallery.hero_text'),
    'image' => setting('gallery.hero_image'), 'imageAlt' => setting('gallery.hero_image_alt'),
]) ?>

<section class="section" aria-label="Photos">
  <div class="container">
<?php if ($albums): ?>
    <?= $this->insert('partials/filter-pills', ['items' => $pills, 'label' => 'Photo albums']) ?>
<?php endif; ?>
<?php if ($album): ?>
    <div class="album-intro">
      <h2><?= e($album['name']) ?></h2>
<?php if (trim((string) ($album['description'] ?? '')) !== ''): ?><p><?= e($album['description']) ?></p><?php endif; ?>
<?php if (!empty($album['event_date'])): ?><p class="muted"><?= icon('calendar3') ?> <time datetime="<?= e(substr((string) $album['event_date'], 0, 10)) ?>"><?= e(format_date((string) $album['event_date'] . ' 00:00:00', 'j F Y')) ?></time></p><?php endif; ?>
<?php if ($external !== ''): ?>
      <p class="notice"><?= icon('box-arrow-up-right') ?><span><a href="<?= e($external) ?>" target="_blank" rel="noopener noreferrer">See the full photo album on Pixieset (opens in a new tab)</a></span></p>
<?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($images): ?>
    <div class="grid-3 gallery-cards" data-gallery>
<?php foreach ($images as $img):
    $label = trim((string) ($img['title'] ?? '')) !== '' ? (string) $img['title'] : (string) $img['category_name']; ?>
      <article class="card-media">
        <div class="card-media__img">
          <a href="<?= e(upload_url($img['image_path'])) ?>" data-lightbox data-alt="<?= e((string) $img['alt_text']) ?>" data-caption="<?= e((string) ($img['caption'] ?? '')) ?>">
            <img src="<?= e(upload_url($img['thumb_path'] ?: $img['image_path'])) ?>"<?= srcset($img['image_path'], '(min-width: 62rem) 360px, (min-width: 40rem) 50vw, 100vw') ?> alt="<?= e((string) $img['alt_text']) ?>" width="<?= (int) $img['width'] ?>" height="<?= (int) $img['height'] ?>" loading="lazy" decoding="async">
          </a>
        </div>
        <div class="card-media__body">
          <div class="card-media__meta">
<?php if (!empty($img['taken_on'])): ?><span><?= icon('calendar3') ?><time datetime="<?= e(substr((string) $img['taken_on'], 0, 10)) ?>"><?= e(format_date((string) $img['taken_on'] . ' 00:00:00', 'j M Y')) ?></time></span><?php endif; ?>
            <span class="badge badge--solid"><?= e($img['category_name']) ?></span>
          </div>
          <h3 class="card-media__title"><?= e($label) ?></h3>
<?php if (trim((string) ($img['caption'] ?? '')) !== ''): ?><p class="card-media__text clamp-2"><?= e($img['caption']) ?></p><?php endif; ?>
        </div>
      </article>
<?php endforeach; ?>
    </div>
    <?= $this->insert('partials/pagination', ['paginator' => $paginator]) ?>
<?php else: ?>
    <?= $this->insert('partials/empty-state', ['icon' => 'images', 'title' => $album ? 'Photos from this album will be added soon.' : 'Photos will be added soon.', 'text' => $album ? '' : 'Please check back shortly.', 'actionLabel' => $album ? 'All photos' : '', 'actionHref' => '/gallery']) ?>
<?php endif; ?>
  </div>
</section>

<?= $this->insert('partials/cta-band', [
    'icon' => 'images', 'heading' => 'Be Part of Our Story',
    'text' => 'Support our mission by volunteering, partnering or making a donation. Together, we can create more opportunities for young people in Ghana.',
    'buttonLabel' => 'Get Involved', 'buttonHref' => '/get-involved', 'script' => 'Skills Build Futures',
]) ?>
