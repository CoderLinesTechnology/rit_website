<?php
/** vars: $metrics, $categories, $programs, $galleryAlbum, $galleryImages, $articles, $applicationsOpen */
// Title, description, canonical and share image come from the controller (SEO settings).
$this->layout('layouts/public');
$metrics = $metrics ?? []; $categories = $categories ?? []; $programs = $programs ?? [];
$galleryImages = $galleryImages ?? []; $articles = $articles ?? []; $applicationsOpen = !empty($applicationsOpen);
$heroImg = (string) setting('home.hero_image', '');
$introImg = (string) setting('home.intro_image', '');
$galleryImg = (string) setting('home.gallery_image', '');
if ($galleryImg === '' && $galleryImages) { $galleryImg = (string) ($galleryImages[0]['image_path'] ?? ''); }
$vision = trim((string) setting('org.vision', ''));
$mission = trim((string) setting('org.mission', ''));
$valuesRaw = trim((string) setting('org.values', ''));
$values = $valuesRaw === '' ? '' : implode(' • ', array_filter(array_map('trim', preg_split('/\R/', $valuesRaw))));
$aboutItems = array_values(array_filter([
    ['eye', 'Our Vision', $vision], ['bullseye', 'Our Mission', $mission], ['star', 'Our Values', $values],
], static fn(array $i): bool => $i[2] !== ''));
$stripItems = $programs ?: $categories;
$stripIsPrograms = (bool) $programs;
$m = count($metrics) > 4 ? array_slice($metrics, 0, 4) : $metrics;

// Organization structured data (only fields we actually have).
$ld = ['@context' => 'https://schema.org', '@type' => 'NGO', 'name' => setting('org.name'), 'url' => (string) config('app.url')];
if (setting('org.legal_name') !== '') { $ld['legalName'] = setting('org.legal_name'); }
$ld['logo'] = upload_url(setting('site.logo_path')) !== '' ? absolute_url(upload_url(setting('site.logo_path'))) : absolute_url('/assets/images/logo.png');
if (setting('contact.email') !== '') { $ld['email'] = setting('contact.email'); }
if (setting('contact.phone') !== '') { $ld['telephone'] = setting('contact.phone'); }
if (preg_match('/^\d{4}$/', setting('org.established_year'))) { $ld['foundingDate'] = setting('org.established_year'); }
$addr = array_map('trim', explode(',', setting('contact.address')));
if (count($addr) >= 4 && $addr[0] !== '') {
    $ld['address'] = ['@type' => 'PostalAddress', 'streetAddress' => $addr[0], 'addressLocality' => $addr[1], 'addressRegion' => $addr[2],
        'addressCountry' => strcasecmp($addr[count($addr) - 1], 'Ghana') === 0 ? 'GH' : $addr[count($addr) - 1]];
}
$sameAs = array_values(array_filter(array_map(static fn (array $l): string => (string) $l['url'], social_links()), static fn (string $u): bool => (bool) preg_match('#^https?://#i', $u)));
if ($sameAs) { $ld['sameAs'] = $sameAs; }
$this->start('head');
?>
<script type="application/ld+json"><?= json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php $this->stop(); ?>

<section class="hero<?= $heroImg === '' ? ' hero--noimg' : '' ?>" aria-labelledby="hero-h">
<?php if ($heroImg !== ''): ?>
  <img class="hero__img" src="<?= e(upload_url($heroImg)) ?>"<?= srcset($heroImg, '100vw') ?> alt="<?= e((string) setting('home.hero_image_alt', '')) ?>" width="1600" height="700" decoding="async" fetchpriority="high">
<?php endif; ?>
  <div class="container hero__inner">
    <div class="hero__copy">
<?php if (setting('home.hero_eyebrow', '') !== ''): ?><span class="eyebrow"><?= e(setting('home.hero_eyebrow')) ?></span><?php endif; ?>
      <h1 id="hero-h"><?= e(setting('home.hero_heading', 'RIT Foundation Ghana')) ?></h1>
<?php if (setting('home.hero_text', '') !== ''): ?><p class="hero__text"><?= e(setting('home.hero_text')) ?></p><?php endif; ?>
      <div class="hero__actions">
        <a class="btn" href="<?= e(url('/programs')) ?>">Explore Our Programs <?= icon('arrow-right') ?></a>
        <a class="btn btn--outline-light" href="<?= e(url('/get-involved')) ?>">Get Involved</a>
<?php if ($applicationsOpen): ?>
        <a class="link-arrow link-light" href="<?= e(url('/apply')) ?>"><span>Apply for training</span> <?= icon('arrow-right') ?></a>
<?php endif; ?>
      </div>
    </div>
<?php if (setting('home.hero_script', '') !== ''): ?>
    <p class="hero__script" aria-hidden="true"><?= e(setting('home.hero_script')) ?></p>
<?php endif; ?>
  </div>
</section>

<section class="section" aria-labelledby="about-h">
  <div class="container about">
    <div class="about__photo<?= $introImg === '' ? ' ph' : '' ?>">
<?php if ($introImg !== ''): ?>
      <img src="<?= e(upload_url($introImg)) ?>"<?= srcset($introImg, '(min-width: 62rem) 30vw, 100vw') ?> alt="<?= e((string) setting('home.intro_image_alt', '')) ?>" width="640" height="480" loading="lazy" decoding="async">
<?php else: ?><?= icon('image') ?><?php endif; ?>
    </div>
    <div class="about__text">
<?php if (setting('home.intro_eyebrow', '') !== ''): ?><span class="eyebrow"><?= e(setting('home.intro_eyebrow')) ?></span><?php endif; ?>
      <h2 id="about-h"><?= e(setting('home.intro_heading', 'Who We Are')) ?></h2>
      <div class="prose"><?= format_text((string) setting('home.intro_text', '')) ?></div>
      <a class="btn" href="<?= e(url('/about')) ?>">Learn More About Us <?= icon('arrow-right') ?></a>
    </div>
<?php if ($aboutItems): ?>
    <div class="about__card about-card">
<?php foreach ($aboutItems as [$ico, $label, $text]): ?>
      <div class="about-card__item"><?= icon($ico) ?><div><h3><?= e($label) ?></h3><p><?= e(excerpt($text, 160)) ?></p></div></div>
<?php endforeach; ?>
    </div>
<?php endif; ?>
  </div>
</section>

<?php if ($m): ?>
<section class="section section--soft" aria-labelledby="impact-h">
  <div class="container impact">
    <div class="impact__copy">
<?php if (setting('home.impact_eyebrow', '') !== ''): ?><span class="eyebrow"><?= e(setting('home.impact_eyebrow')) ?></span><?php endif; ?>
      <h2 id="impact-h"><?= e(setting('home.impact_heading', 'Creating Real Change')) ?></h2>
<?php if (setting('home.impact_text', '') !== ''): ?><p><?= e(setting('home.impact_text')) ?></p><?php endif; ?>
      <a class="btn" href="<?= e(url('/impact')) ?>">See Our Impact <?= icon('arrow-right') ?></a>
    </div>
    <ul class="metrics" data-cols="<?= count($m) ?>">
<?php foreach ($m as $row): ?>
      <li class="metric">
        <?= icon((string) (($row['icon'] ?? '') !== '' ? $row['icon'] : 'people-fill')) ?>
        <span class="metric__value"><?php if (!empty($row['is_approximate'])): ?><span class="visually-hidden">Approximately </span><?php endif; ?><?= e($row['value']) ?></span>
        <span class="metric__label"><?= e($row['label']) ?></span>
<?php if (!empty($row['is_approximate'])): ?><span class="metric__approx" aria-hidden="true">approx.</span><?php endif; ?>
      </li>
<?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<?php if ($stripItems): ?>
<section class="section" aria-labelledby="programs-h">
  <div class="container">
    <div class="section__head section__head--row">
      <div>
<?php if (setting('home.programs_eyebrow', '') !== ''): ?><span class="eyebrow"><?= e(setting('home.programs_eyebrow')) ?></span><?php endif; ?>
        <h2 id="programs-h"><?= e(setting('home.programs_heading', 'Our Programs')) ?></h2>
      </div>
      <a class="link-arrow" href="<?= e(url('/programs')) ?>"><span>View All Programs</span> <?= icon('arrow-right') ?></a>
    </div>
    <div class="strip-panel">
      <ul class="mini-grid">
<?php foreach (array_slice($stripItems, 0, 6) as $p):
    $title = (string) ($p['title'] ?? $p['name'] ?? '');
    $href = $stripIsPrograms ? '/programs/' . $p['slug'] : '/programs?category=' . $p['slug'];
    $thumb = (string) ($p['featured_thumb_path'] ?? $p['thumb_path'] ?? '');
    $alt = (string) ($p['featured_image_alt'] ?? $p['image_alt'] ?? '');
    $sum = (string) ($p['summary'] ?? $p['description'] ?? ''); ?>
        <li class="mini">
          <div class="mini__media">
            <div class="mini__img<?= $thumb === '' ? ' ph' : '' ?>">
<?php if ($thumb !== ''): ?><img src="<?= e(upload_url($thumb)) ?>"<?= srcset($thumb, '(min-width: 62rem) 190px, (min-width: 40rem) 33vw, 50vw') ?> alt="<?= e($alt) ?>" width="400" height="300" loading="lazy" decoding="async"><?php else: ?><?= icon('image') ?><?php endif; ?>
            </div>
            <span class="icon-badge"><?= icon((string) (($p['icon'] ?? '') !== '' ? $p['icon'] : 'lightbulb')) ?></span>
          </div>
          <h3><a href="<?= e(url($href)) ?>"><?= e($title) ?></a></h3>
          <p><?= e(excerpt($sum, 90)) ?></p>
        </li>
<?php endforeach; ?>
      </ul>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section section--soft" aria-label="Gallery and news">
  <div class="container duo">
    <div>
      <div class="feature<?= $galleryImg === '' ? ' feature--noimg' : '' ?>">
<?php if ($galleryImg !== ''): ?>
        <img class="feature__img" src="<?= e(upload_url($galleryImg)) ?>"<?= srcset($galleryImg, '(min-width: 62rem) 50vw, 100vw') ?> alt="<?= e((string) setting('home.gallery_image_alt', '')) ?>" width="800" height="600" loading="lazy" decoding="async">
<?php else: ?>
        <div class="feature__img-ph ph"><?= icon('images') ?></div>
<?php endif; ?>
        <div class="feature__card">
          <span class="feature__icon"><?= icon('images') ?></span>
          <div class="feature__body">
<?php if (setting('home.gallery_eyebrow', '') !== ''): ?><span class="eyebrow"><?= e(setting('home.gallery_eyebrow')) ?></span><?php endif; ?>
            <h3><?= e(setting('home.gallery_heading', 'Moments That Inspire')) ?></h3>
<?php if (setting('home.gallery_text', '') !== ''): ?><p><?= e(setting('home.gallery_text')) ?></p><?php endif; ?>
            <a class="btn btn--outline-light btn--sm" href="<?= e(url('/gallery')) ?>">View Gallery <?= icon('arrow-right') ?></a>
          </div>
        </div>
      </div>
<?php if (!empty($galleryAlbum)): ?>
      <div class="teaser"<?= $galleryImages ? ' data-gallery' : '' ?>>
<?php if ($galleryImages): ?>
        <ul class="gallery-grid">
<?php foreach (array_slice($galleryImages, 0, 4) as $g): ?>
          <li><a class="gallery-item" href="<?= e(upload_url($g['image_path'])) ?>" data-lightbox data-alt="<?= e((string) $g['alt_text']) ?>" data-caption="<?= e((string) ($g['caption'] ?? '')) ?>"><img src="<?= e(upload_url($g['thumb_path'] ?: $g['image_path'])) ?>"<?= srcset($g['image_path'], '(min-width: 62rem) 140px, 25vw') ?> alt="<?= e((string) $g['alt_text']) ?>" width="<?= (int) $g['width'] ?>" height="<?= (int) $g['height'] ?>" loading="lazy" decoding="async"></a></li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
        <p><a class="link-arrow" href="<?= e(url('/gallery/' . $galleryAlbum['slug'])) ?>"><span><?= e($galleryAlbum['name']) ?> &mdash; view album</span> <?= icon('arrow-right') ?></a></p>
<?php if (!empty($galleryAlbum['external_url'])): ?>
        <p><a class="link-arrow" href="<?= e($galleryAlbum['external_url']) ?>" target="_blank" rel="noopener noreferrer"><span>Full photo gallery on Pixieset (opens in a new tab)</span> <?= icon('box-arrow-up-right') ?></a></p>
<?php endif; ?>
      </div>
<?php endif; ?>
    </div>
    <div>
      <div class="news-head">
        <h2><?= e(setting('home.news_heading', 'Latest News & Success Stories')) ?></h2>
        <a class="link-arrow" href="<?= e(url('/news')) ?>"><span>View All News</span> <?= icon('arrow-right') ?></a>
      </div>
<?php if ($articles): ?>
      <ul class="news-list">
<?php foreach (array_slice($articles, 0, 3) as $a): $th = (string) ($a['featured_thumb_path'] ?? ''); ?>
        <li class="news-item">
          <div class="news-item__img<?= $th === '' ? ' ph' : '' ?>"><?php if ($th !== ''): ?><img src="<?= e(upload_url($th)) ?>"<?= srcset($th, '120px') ?> alt="<?= e((string) ($a['featured_image_alt'] ?? '')) ?>" width="180" height="120" loading="lazy" decoding="async"><?php else: ?><?= icon('newspaper') ?><?php endif; ?></div>
          <div>
            <time datetime="<?= e(format_date($a['published_at'], 'Y-m-d')) ?>"><?= e(format_date($a['published_at'], 'M j, Y')) ?></time>
            <h3><a href="<?= e(url('/news/' . $a['slug'])) ?>"><?= e($a['title']) ?></a></h3>
          </div>
        </li>
<?php endforeach; ?>
      </ul>
<?php else: ?>
      <p class="muted">News and success stories will appear here soon.</p>
<?php endif; ?>
    </div>
  </div>
</section>

<section class="section section--dark" aria-labelledby="involve-h">
  <div class="container involve">
    <div class="involve__lead">
      <?= icon('person-raised-hand') ?>
      <div>
        <h2 id="involve-h"><?= e(setting('home.involve_heading', 'Get Involved')) ?></h2>
<?php if (setting('home.involve_text', '') !== ''): ?><p><?= e(setting('home.involve_text')) ?></p><?php endif; ?>
      </div>
    </div>
    <ul class="tiles">
      <li><a class="tile" href="<?= e(url('/partner')) ?>"><?= icon('people') ?><span>Partner With Us</span></a></li>
      <li><a class="tile" href="<?= e(url('/volunteer')) ?>"><?= icon('heart') ?><span>Volunteer</span></a></li>
      <li><a class="tile" href="<?= e(url('/apply')) ?>"><?= icon('file-earmark-text') ?><span>Apply for Training</span></a></li>
      <li><a class="tile tile--solid" href="<?= e(url('/donate')) ?>"><?= icon('heart-fill') ?><span>Make a Donation</span></a></li>
    </ul>
  </div>
</section>
