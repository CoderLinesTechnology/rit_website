<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e(($title ?? 'Sign in') . ' | RIT Foundation admin') ?></title>
<link rel="icon" href="/assets/images/favicon-32.png" sizes="32x32" type="image/png">
<link rel="icon" href="/assets/images/favicon-192.png" sizes="192x192" type="image/png">
<link rel="apple-touch-icon" href="/assets/images/apple-touch-icon.png">
<link rel="preload" href="/assets/fonts/inter-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</head>
<body>
<main class="auth" id="main">
  <div class="auth__card">
    <a class="auth__brand" href="<?= e(url('/')) ?>"><picture><source srcset="<?= e(asset('images/logo.webp')) ?>" type="image/webp"><img src="<?= e(asset('images/logo.png')) ?>" alt="RIT Foundation Ghana" width="311" height="192"></picture><small>Administration</small></a>
<?= $this->insert('partials/flash') ?>
<?= $this->section('content') ?>
  </div>
</main>
</body>
</html>
