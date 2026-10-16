<?php
$this->layout('layouts/error', ['title' => 'Method not allowed']);
$message = isset($message) ? trim((string) $message) : '';
?>
<section class="error-page">
  <div class="container container--narrow">
    <span class="error-page__code" aria-hidden="true">405</span>
    <h1>That action is not available here</h1>
    <p class="lead">The page does not support the request that was made. Please go back and try again.</p>
    <?php if ($message !== ''): ?><p><?= e($message) ?></p><?php endif; ?>
    <ul class="error-page__links">
      <li><a class="btn" href="/">Go to the home page</a></li>
      <li><a class="btn btn--secondary" href="/contact">Contact us</a></li>
    </ul>
  </div>
</section>
