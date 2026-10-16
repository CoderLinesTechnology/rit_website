<?php /* Standalone: must not touch the database (no setting(), social_links(), etc.). */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e(($title ?? 'Something went wrong') . ' | RIT Foundation Ghana') ?></title>
<meta name="theme-color" content="#9D1D2A">
<link rel="icon" href="/assets/images/favicon-32.png" sizes="32x32" type="image/png">
<link rel="icon" href="/assets/images/favicon-192.png" sizes="192x192" type="image/png">
<link rel="apple-touch-icon" href="/assets/images/apple-touch-icon.png">
<link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>
<header class="site-header">
  <div class="container site-header__inner">
    <a class="brand" href="/"><picture><source srcset="/assets/images/logo.webp" type="image/webp"><img class="brand__logo" src="/assets/images/logo.png" alt="RIT Foundation Ghana" width="311" height="192"></picture></a>
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
