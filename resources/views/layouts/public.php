<?php
/** @var string $title */
$orgName = 'RIT Foundation Ghana';
$pageTitle = trim((string) ($title ?? ''));
$fullTitle = ($pageTitle === '' || $pageTitle === $orgName || str_contains($pageTitle, $orgName)) ? ($pageTitle !== '' ? $pageTitle : $orgName) : $pageTitle . ' | ' . $orgName;
$metaDescription = trim((string) ($description ?? '')) !== '' ? (string) $description : (string) setting('org.tagline', '');
$canonicalUrl = isset($canonical) && $canonical !== null && $canonical !== '' ? absolute_url((string) $canonical) : '';
$ogImageUrl = !empty($ogImage) ? absolute_url('/' . ltrim((string) $ogImage, '/')) : '';
$robotsValue = !config('app.indexable') ? 'noindex,nofollow' : (!empty($robots) ? (string) $robots : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($fullTitle) ?></title>
<?php if ($metaDescription !== ''): ?>
<meta name="description" content="<?= e($metaDescription) ?>">
<?php endif; ?>
<?php if ($robotsValue !== ''): ?>
<meta name="robots" content="<?= e($robotsValue) ?>">
<?php endif; ?>
<?php if ($canonicalUrl !== ''): ?>
<link rel="canonical" href="<?= e($canonicalUrl) ?>">
<meta property="og:url" content="<?= e($canonicalUrl) ?>">
<?php endif; ?>
<meta property="og:site_name" content="<?= e($orgName) ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($fullTitle) ?>">
<?php if ($metaDescription !== ''): ?>
<meta property="og:description" content="<?= e($metaDescription) ?>">
<?php endif; ?>
<?php if ($ogImageUrl !== ''): ?>
<meta property="og:image" content="<?= e($ogImageUrl) ?>">
<meta name="twitter:card" content="summary_large_image">
<?php endif; ?>
<meta name="theme-color" content="#9D1D2A">
<link rel="icon" href="/assets/images/favicon.svg" type="image/svg+xml">
<link rel="preload" href="/assets/fonts/inter-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/poppins-latin-700-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/main.css')) ?>">
<script><?= App\Core\Response::JS_FLAG_SCRIPT ?></script>
<script src="<?= e(asset('js/main.js')) ?>" defer></script>
<?= $this->section('head') ?>
</head>
<body<?= !empty($bodyClass) ? ' class="' . e($bodyClass) . '"' : '' ?><?= !empty($pageKey) ? ' data-page="' . e($pageKey) . '"' : '' ?>>
<a class="skip-link" href="#main">Skip to main content</a>
<?= $this->insert('partials/header', ['pageKey' => $pageKey ?? '']) ?>
<main id="main" tabindex="-1">
<?= $this->insert('partials/flash') ?>
<?= $this->section('content') ?>
</main>
<?= $this->insert('partials/footer') ?>
<?= $this->section('scripts') ?>
</body>
</html>
