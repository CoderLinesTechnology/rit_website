<?php
/** vars: $program (row incl. category_name/category_slug, applications_open), $images, $related */
// Title, description, canonical and share image come from the controller (program SEO fields).
$this->layout('layouts/public');
$images = $images ?? []; $related = $related ?? [];
$open = !empty($program['applications_open']);
$date = static fn (?string $d): string => ($d !== null && $d !== '') ? format_date($d . ' 00:00:00', 'j F Y') : '';
$opens = $date($program['application_opens_on'] ?? null);
$closes = $date($program['application_closes_on'] ?? null);
$window = $opens !== '' && $closes !== '' ? $opens . ' – ' . $closes : ($closes !== '' ? 'Until ' . $closes : ($opens !== '' ? 'From ' . $opens : ''));
$blocks = [
    'Who it’s for' => (string) ($program['target_audience'] ?? ''),
    'Eligibility' => (string) ($program['eligibility'] ?? ''),
    'How to apply' => (string) ($program['application_instructions'] ?? ''),
];
?>
<?= $this->insert('partials/page-hero', [
    'breadcrumbs' => [['Home', '/'], ['Programs', '/programs'], [(string) $program['title'], null]],
    'eyebrow' => (string) ($program['category_name'] ?? ''), 'title' => (string) $program['title'],
    'text' => (string) $program['summary'],
    'image' => (string) ($program['featured_image_path'] ?? ''), 'imageAlt' => (string) ($program['featured_image_alt'] ?? ''),
]) ?>

<section class="section" aria-label="Program details">
  <div class="container news-layout">
    <div>
      <div class="prose prose--wide"><?= format_text((string) $program['description']) ?></div>
<?php foreach ($blocks as $heading => $text): if (trim($text) === '') { continue; } ?>
      <div class="prose prose--wide program-block">
        <h2><?= e($heading) ?></h2>
        <?= format_text($text) ?>
      </div>
<?php endforeach; ?>
<?php if ($images): ?>
      <h2 class="program-block">Photos</h2>
      <ul class="gallery-grid" data-gallery>
<?php foreach ($images as $g): ?>
        <li><a class="gallery-item" href="<?= e(upload_url($g['image_path'])) ?>" data-lightbox data-alt="<?= e((string) $g['alt_text']) ?>" data-caption="<?= e((string) ($g['caption'] ?? '')) ?>"><img src="<?= e(upload_url($g['thumb_path'] ?: $g['image_path'])) ?>"<?= srcset($g['image_path'], '(min-width: 62rem) 200px, 33vw') ?> alt="<?= e((string) $g['alt_text']) ?>" width="<?= (int) $g['width'] ?>" height="<?= (int) $g['height'] ?>" loading="lazy" decoding="async"></a></li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
    </div>
    <aside class="news-side" aria-label="Program details">
      <div class="sidebar-card">
        <h2>Program details</h2>
        <dl class="detail-list">
<?php if (!empty($program['category_name'])): ?><div><dt>Category</dt><dd><?= e($program['category_name']) ?></dd></div><?php endif; ?>
<?php if (trim((string) ($program['duration'] ?? '')) !== ''): ?><div><dt>Duration</dt><dd><?= e($program['duration']) ?></dd></div><?php endif; ?>
<?php if (trim((string) ($program['location'] ?? '')) !== ''): ?><div><dt>Location</dt><dd><?= e($program['location']) ?></dd></div><?php endif; ?>
<?php if ($window !== ''): ?><div><dt>Application window</dt><dd><?= e($window) ?></dd></div><?php endif; ?>
          <div><dt>Status</dt><dd><span class="badge <?= $open ? 'badge--primary' : '' ?>"><?= $open ? 'Applications open' : 'Applications closed' ?></span></dd></div>
        </dl>
<?php if ($open): ?>
        <a class="btn" href="<?= e(url('/apply', ['program' => $program['slug']])) ?>">Apply for this program <?= icon('arrow-right') ?></a>
<?php else: ?>
        <p class="muted">Applications for this program are not open at the moment.</p>
        <a class="link-arrow" href="<?= e(url('/programs')) ?>"><span>See all programs</span> <?= icon('arrow-right') ?></a>
<?php endif; ?>
      </div>
    </aside>
  </div>
</section>

<?php if ($related): ?>
<section class="section section--soft" aria-labelledby="related-h">
  <div class="container">
    <h2 id="related-h">Related programs</h2>
    <div class="grid-3">
<?php foreach ($related as $p): $th = (string) ($p['featured_thumb_path'] ?? ''); ?>
      <article class="pcard">
        <div class="pcard__media">
          <div class="pcard__img<?= upload_url($th) === '' ? ' ph' : '' ?>"><?php if (upload_url($th) !== ''): ?><img src="<?= e(upload_url($th)) ?>"<?= srcset($th, '(min-width: 62rem) 360px, (min-width: 40rem) 50vw, 100vw') ?> alt="<?= e((string) ($p['featured_image_alt'] ?? '')) ?>" width="640" height="360" loading="lazy" decoding="async"><?php else: ?><?= icon('image') ?><?php endif; ?></div>
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
  </div>
</section>
<?php endif; ?>

<?= $this->insert('partials/cta-band', [
    'icon' => 'mortarboard-fill',
    'heading' => (string) setting('programs.cta_heading', 'Ready to Build Your Future?'),
    'text' => (string) setting('programs.cta_text', ''),
    'buttonLabel' => 'Apply for Training', 'buttonHref' => '/apply',
    'image' => (string) setting('programs.cta_image', setting('site.cta_image', '')),
    'imageAlt' => (string) setting('programs.cta_image_alt', setting('site.cta_image_alt', '')),
    'script' => (string) setting('home.hero_script', ''),
]) ?>
