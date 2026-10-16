<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e(($title ?? 'Sign in') . ' | RIT Foundation admin') ?></title>
<link rel="icon" href="/assets/images/favicon.svg" type="image/svg+xml">
<link rel="preload" href="/assets/fonts/inter-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</head>
<body>
<main class="auth" id="main">
  <div class="auth__card">
    <a class="auth__brand" href="<?= e(url('/')) ?>">RIT <b>Foundation</b><small>Ghana &middot; Admin</small></a>
<?= $this->insert('partials/flash') ?>
<?= $this->section('content') ?>
  </div>
</main>
</body>
</html>
