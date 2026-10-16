<div class="empty-state">
  <?= icon($icon ?? 'inboxes') ?>
  <h2><?= e($title) ?></h2>
<?php if (!empty($text)): ?><p><?= e($text) ?></p><?php endif; ?>
<?php if (!empty($actionLabel) && !empty($actionHref)): ?><a class="btn" href="<?= e(url($actionHref)) ?>"><?= e($actionLabel) ?></a><?php endif; ?>
</div>
