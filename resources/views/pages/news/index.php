<?php
/** vars: $categories (with articles), $category, $q, $articles, $paginator, $stories (list|null), $latest */
// Title, description, canonical and share image come from the controller (SEO settings).
$this->layout('layouts/public');
$categories = $categories ?? []; $category = $category ?? null; $q = $q ?? ''; $articles = $articles ?? [];
$stories = $stories ?? []; $latest = $latest ?? [];
$default = $category === null && $q === '';
$crumbs = $category ? [['Home', '/'], ['News & Success Stories', '/news'], [(string) $category['name'], null]] : [['Home', '/'], ['News & Success Stories', null]];
$pills = [['All', url('/news'), $default]];
foreach ($categories as $c) { $pills[] = [(string) $c['name'], url('/news/category/' . $c['slug']), $category !== null && $category['slug'] === $c['slug']]; }
$whatsapp = '';
foreach (social_links() as $s) { if ($s['platform'] === 'whatsapp' && preg_match('#^https?://#i', (string) $s['url'])) { $whatsapp = (string) $s['url']; break; } }
$card = static function (array $a): string {
    $th = (string) ($a['featured_thumb_path'] ?: $a['featured_image_path']);
    $o = '<article class="card-media"><div class="card-media__img' . (upload_url($th) === '' ? ' ph' : '') . '">';
    $o .= upload_url($th) !== ''
        ? '<img src="' . e(upload_url($th)) . '"' . srcset($th, '(min-width: 62rem) 360px, (min-width: 40rem) 50vw, 100vw') . ' alt="' . e((string) ($a['featured_image_alt'] ?? '')) . '" width="640" height="400" loading="lazy" decoding="async">'
        : icon('newspaper');
    $o .= '</div><div class="card-media__body"><div class="card-media__meta">';
    if (!empty($a['category_name'])) { $o .= '<span class="badge badge--solid">' . e($a['category_name']) . '</span>'; }
    $o .= '<span><time datetime="' . e(iso_date($a['published_at'])) . '">' . e(format_date($a['published_at'], 'j M Y')) . '</time></span></div>';
    $o .= '<h3 class="card-media__title"><a href="' . e(url('/news/' . $a['slug'])) . '">' . e($a['title']) . '</a></h3>';
    if (trim((string) ($a['excerpt'] ?? '')) !== '') { $o .= '<p class="card-media__text clamp-3">' . e(excerpt((string) $a['excerpt'], 140)) . '</p>'; }
    return $o . '<span class="link-arrow"><span>Read More</span> ' . icon('arrow-right') . '</span></div></article>';
};
$heading = $category ? (string) $category['name'] : ($q !== '' ? 'Search results for “' . $q . '”' : 'Latest News');
?>
<?= $this->insert('partials/page-hero', [
    'breadcrumbs' => $crumbs,
    'eyebrow' => setting('news.hero_eyebrow'), 'title' => setting('news.hero_heading'),
    'text' => setting('news.hero_text'),
    'image' => setting('news.hero_image'), 'imageAlt' => setting('news.hero_image_alt'),
]) ?>

<section class="section" aria-label="News">
  <div class="container">
    <?= $this->insert('partials/filter-pills', ['items' => $pills, 'label' => 'News categories', 'search' => ['action' => '/news', 'value' => $q, 'placeholder' => 'Search news']]) ?>
    <div class="news-layout">
      <div>
        <h2><?= e($heading) ?></h2>
<?php if ($category && trim((string) ($category['description'] ?? '')) !== ''): ?><p class="muted"><?= e($category['description']) ?></p><?php endif; ?>
<?php if ($articles): ?>
        <div class="grid-3 news-grid">
<?php foreach ($articles as $a): ?>
          <?= $card($a) ?>
<?php endforeach; ?>
        </div>
        <?= $this->insert('partials/pagination', ['paginator' => $paginator]) ?>
<?php else: ?>
        <?= $this->insert('partials/empty-state', ['icon' => 'newspaper',
            'title' => $q !== '' ? 'No articles match your search.' : 'No articles here yet.',
            'text' => $q !== '' ? 'Try a different word, or browse all news.' : 'Please check back soon.',
            'actionLabel' => 'All news', 'actionHref' => '/news']) ?>
<?php endif; ?>
<?php if ($default && $stories): ?>
        <h2 class="news-subhead">Success Stories</h2>
        <div class="grid-3 news-grid">
<?php foreach ($stories as $a): ?>
          <?= $card($a) ?>
<?php endforeach; ?>
        </div>
<?php endif; ?>
      </div>
      <aside class="news-side" aria-label="Sidebar">
<?php if ($latest): ?>
        <div class="sidebar-card">
          <h2>Latest Updates</h2>
<?php foreach ($latest as $a): $th = (string) ($a['featured_thumb_path'] ?: $a['featured_image_path']); ?>
          <div class="list-row">
            <?php if (upload_url($th) !== ''): ?><img src="<?= e(upload_url($th)) ?>" alt="" width="70" height="60" loading="lazy" decoding="async"><?php else: ?><span class="ph list-row__ph"><?= icon('newspaper') ?></span><?php endif; ?>
            <div>
              <a href="<?= e(url('/news/' . $a['slug'])) ?>"><?= e($a['title']) ?></a>
              <div class="list-row__meta"><time datetime="<?= e(iso_date($a['published_at'])) ?>"><?= e(format_date($a['published_at'], 'j M Y')) ?></time><?php if (!empty($a['category_name'])): ?> <span class="badge badge--solid"><?= e($a['category_name']) ?></span><?php endif; ?></div>
            </div>
            <?= icon('chevron-right') ?>
          </div>
<?php endforeach; ?>
        </div>
<?php endif; ?>
        <div class="sidebar-card sidebar-card--pink">
          <span class="icon-circle"><?= icon('envelope') ?></span>
          <h2>Never Miss an Update</h2>
          <p>Follow RIT Foundation Ghana on social media and our WhatsApp Channel for the latest news and events.</p>
<?php if ($whatsapp !== ''): ?>
          <a class="btn btn--sm" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener noreferrer">Follow on WhatsApp <?= icon('arrow-right') ?></a>
<?php else: ?>
          <a class="btn btn--sm" href="<?= e(url('/contact')) ?>">Follow on WhatsApp <?= icon('arrow-right') ?></a>
<?php endif; ?>
        </div>
      </aside>
    </div>
  </div>
</section>

<?= $this->insert('partials/cta-band', [
    'icon' => 'images', 'heading' => 'Be Part of Our Story',
    'text' => 'Support our mission by volunteering, partnering or making a donation. Together, we can create more opportunities for young people in Ghana.',
    'buttonLabel' => 'Get Involved', 'buttonHref' => '/get-involved', 'script' => 'Skills Build Futures',
]) ?>
