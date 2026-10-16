<?php
$this->layout('layouts/error', ['title' => 'Page not found']);
$message = isset($message) ? trim((string) $message) : '';
?>
<section class="error-page">
  <div class="container container--narrow">
    <span class="error-page__code" aria-hidden="true">404</span>
    <h1>We could not find that page</h1>
    <p class="lead">The page may have moved or the address may have a typo. Try one of these instead.</p>
    <?php if ($message !== ''): ?><p><?= e($message) ?></p><?php endif; ?>
    <ul class="error-page__links">
      <li><a class="btn" href="/">Home</a></li>
      <li><a class="btn btn--secondary" href="/programs">Programs</a></li>
      <li><a class="btn btn--secondary" href="/news">News &amp; Stories</a></li>
      <li><a class="btn btn--secondary" href="/contact">Contact</a></li>
    </ul>
  </div>
</section>
