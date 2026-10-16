<?php
$this->layout('layouts/error', ['title' => 'Access denied']);
$message = isset($message) ? trim((string) $message) : '';
?>
<section class="error-page">
  <div class="container container--narrow">
    <span class="error-page__code" aria-hidden="true">403</span>
    <h1>You do not have access to this page</h1>
    <p class="lead">If you think this is a mistake, please contact us and we will help.</p>
    <?php if ($message !== ''): ?><p><?= e($message) ?></p><?php endif; ?>
    <ul class="error-page__links">
      <li><a class="btn" href="/">Go to the home page</a></li>
      <li><a class="btn btn--secondary" href="/contact">Contact us</a></li>
    </ul>
  </div>
</section>
