<?php
$this->layout('layouts/error', ['title' => 'Something went wrong']);
$message = isset($message) ? trim((string) $message) : '';
?>
<section class="error-page">
  <div class="container container--narrow">
    <span class="error-page__code" aria-hidden="true">500</span>
    <h1>Sorry, something went wrong on our side</h1>
    <p class="lead">We are sorry. Please try again in a moment. If the problem continues, let us know.</p>
    <?php if ($message !== ''): ?><p><?= e($message) ?></p><?php endif; ?>
    <ul class="error-page__links">
      <li><a class="btn" href="/">Go to the home page</a></li>
      <li><a class="btn btn--secondary" href="/contact">Contact us</a></li>
    </ul>
  </div>
</section>
