<?php
$this->layout('layouts/error', ['title' => 'Page expired']);
$message = isset($message) ? trim((string) $message) : '';
?>
<section class="error-page">
  <div class="container container--narrow">
    <span class="error-page__code" aria-hidden="true">419</span>
    <h1>Your form has expired</h1>
    <p class="lead">For your security, forms expire after a while. Please go back, refresh the page and submit it again. Nothing you typed was saved.</p>
    <?php if ($message !== ''): ?><p><?= e($message) ?></p><?php endif; ?>
    <ul class="error-page__links">
      <li><a class="btn" href="/">Go to the home page</a></li>
      <li><a class="btn btn--secondary" href="/contact">Contact us</a></li>
    </ul>
  </div>
</section>
