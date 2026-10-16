<?php
$errs = form_errors();
if (!$errs) { return; }
?>
<div class="error-summary" role="alert" tabindex="-1" id="error-summary" aria-labelledby="error-summary-h">
  <h2 id="error-summary-h">Please correct the following</h2>
  <ul>
<?php foreach ($errs as $field => $msg): ?>
    <li><a href="#<?= e('field-' . $field) ?>"><?= e($msg) ?></a></li>
<?php endforeach; ?>
  </ul>
</div>
