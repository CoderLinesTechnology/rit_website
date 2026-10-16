<?php /* Standalone: must not touch the database (no setting(), social_links(), etc.). */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e(($title ?? 'Something went wrong') . ' | RIT Foundation Ghana') ?></title>
<meta name="theme-color" content="#9D1D2A">
<link rel="icon" href="/assets/images/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>
<header class="site-header">
  <div class="container site-header__inner">
    <a class="brand" href="/"><span class="brand__mark">RIT <b>Foundation</b><span class="brand__sub">Ghana</span></span></a>
    <nav aria-label="Primary" class="site-nav">
      <ul><li><a href="/">Home</a></li><li><a href="/contact">Contact</a></li></ul>
    </nav>
  </div>
</header>
<main id="main" tabindex="-1">
<?= $this->section('content') ?>
</main>
<footer class="site-footer">
  <div class="site-footer__base"><div class="container"><span>&copy; <?= date('Y') ?> RIT Foundation Ghana</span><a href="/">Back to the home page</a></div></div>
</footer>
</body>
</html>
