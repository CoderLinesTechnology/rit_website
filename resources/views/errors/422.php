<?php
$this->layout('layouts/error', ['title' => 'Submission not accepted']);
$message = isset($message) ? trim((string) $message) : '';
?>
<section class="error-page">
  <div class="container container--narrow">
    <span class="error-page__code" aria-hidden="true">422</span>
    <h1>We could not accept that submission</h1>
    <p class="lead">Something in the form could not be processed. Please go back, check your details and try again.</p>
    <?php if ($message !== ''): ?><p><?= e($message) ?></p><?php endif; ?>
    <ul class="error-page__links">
      <li><a class="btn" href="/">Go to the home page</a></li>
      <li><a class="btn btn--secondary" href="/contact">Contact us</a></li>
    </ul>
  </div>
</section>
