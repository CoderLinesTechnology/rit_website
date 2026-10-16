<?php
/** vars: $article (row incl. category_name/category_slug), $more (related cards) */
// Title, description, canonical and share image come from the controller / article SEO fields.
$this->layout('layouts/public');
$more = $more ?? [];
$img = (string) ($article['featured_image_path'] ?? '');
$iso = static function (?string $utc): string {
    try { return (new DateTimeImmutable((string) $utc, new DateTimeZone('UTC')))->format('c'); } catch (Exception) { return ''; }
};
$ld = [
    '@context' => 'https://schema.org',
    '@type' => 'NewsArticle',
    'headline' => (string) $article['title'],
    'datePublished' => $iso($article['published_at']),
    'dateModified' => $iso($article['updated_at'] ?? $article['published_at']),
    'mainEntityOfPage' => absolute_url('/news/' . $article['slug']),
    'author' => trim((string) ($article['author_name'] ?? '')) !== ''
        ? ['@type' => 'Person', 'name' => (string) $article['author_name']]
        : ['@type' => 'Organization', 'name' => 'RIT Foundation Ghana'],
    'publisher' => ['@type' => 'Organization', 'name' => 'RIT Foundation Ghana', 'url' => absolute_url('/')],
];
if (upload_url($img) !== '') { $ld['image'] = [absolute_url(upload_url($img))]; }
$this->start('head');
?>
<script type="application/ld+json"><?= json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php $this->stop(); ?>

<section class="article-head">
  <div class="container container--narrow">
    <?= $this->insert('partials/breadcrumbs', ['breadcrumbs' => [['Home', '/'], ['News', '/news'], [(string) $article['title'], null]]]) ?>
<?php if (!empty($article['category_name'])): ?>
    <a class="badge badge--solid" href="<?= e(url('/news/category/' . $article['category_slug'])) ?>"><?= e($article['category_name']) ?></a>
<?php endif; ?>
    <h1><?= e($article['title']) ?></h1>
    <p class="article-meta"><time datetime="<?= e(iso_date($article['published_at'])) ?>"><?= e(format_date($article['published_at'])) ?></time><?php if (trim((string) ($article['author_name'] ?? '')) !== ''): ?> &middot; By <?= e($article['author_name']) ?><?php endif; ?></p>
  </div>
</section>

<article class="section section--flush" aria-label="<?= e($article['title']) ?>">
  <div class="container container--narrow">
<?php if (upload_url($img) !== ''): ?>
    <figure class="article-figure">
      <img src="<?= e(upload_url($img)) ?>"<?= srcset($img, '(min-width: 62rem) 800px, 100vw') ?> alt="<?= e((string) ($article['featured_image_alt'] ?? '')) ?>" width="1200" height="675" decoding="async" fetchpriority="high">
    </figure>
<?php endif; ?>
    <div class="prose prose--article"><?= format_text((string) $article['content']) ?></div>
    <p class="article-back"><a class="link-arrow" href="<?= e(url('/news')) ?>"><?= icon('chevron-left') ?><span>Back to all news</span></a></p>
  </div>
</article>

<?php if ($more): ?>
<section class="section section--soft" aria-labelledby="more-h">
  <div class="container">
    <h2 id="more-h">More stories</h2>
    <div class="grid-3 news-grid">
<?php foreach ($more as $a): $th = (string) ($a['featured_thumb_path'] ?: $a['featured_image_path']); ?>
      <article class="card-media">
        <div class="card-media__img<?= upload_url($th) === '' ? ' ph' : '' ?>"><?php if (upload_url($th) !== ''): ?><img src="<?= e(upload_url($th)) ?>"<?= srcset($th, '(min-width: 62rem) 360px, (min-width: 40rem) 50vw, 100vw') ?> alt="<?= e((string) ($a['featured_image_alt'] ?? '')) ?>" width="640" height="400" loading="lazy" decoding="async"><?php else: ?><?= icon('newspaper') ?><?php endif; ?></div>
        <div class="card-media__body">
          <div class="card-media__meta">
<?php if (!empty($a['category_name'])): ?><span class="badge badge--solid"><?= e($a['category_name']) ?></span><?php endif; ?>
            <span><time datetime="<?= e(iso_date($a['published_at'])) ?>"><?= e(format_date($a['published_at'], 'j M Y')) ?></time></span>
          </div>
          <h3 class="card-media__title"><a href="<?= e(url('/news/' . $a['slug'])) ?>"><?= e($a['title']) ?></a></h3>
<?php if (trim((string) ($a['excerpt'] ?? '')) !== ''): ?><p class="card-media__text clamp-3"><?= e(excerpt((string) $a['excerpt'], 140)) ?></p><?php endif; ?>
          <span class="link-arrow"><span>Read More</span> <?= icon('arrow-right') ?></span>
        </div>
      </article>
<?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
