<div class="empty-state">
  <?= icon($icon ?? 'info-circle') ?>
  <h2><?= e($title) ?></h2>
<?php if (!empty($text)): ?><p><?= e($text) ?></p><?php endif; ?>
<?php if (!empty($actionLabel) && !empty($actionHref)): ?>
  <a class="btn btn--secondary" href="<?= e(url($actionHref)) ?>"><?= e($actionLabel) ?></a>
<?php endif; ?>
</div>
