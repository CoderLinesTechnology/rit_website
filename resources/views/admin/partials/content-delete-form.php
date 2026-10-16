<?php
/** Icon delete button as a POST form with a confirmation. vars: action, confirm, label (accessible name), ?returnTo, ?text (shows a text button instead of the icon) */
?>
<form method="post" action="<?= e(url($action)) ?>" data-confirm="<?= e($confirm) ?>" data-once>
  <?= csrf_field() ?>
<?php if (!empty($returnTo)): ?><input type="hidden" name="return_to" value="<?= e($returnTo) ?>"><?php endif; ?>
<?php if (!empty($text)): ?>
  <button type="submit" class="btn btn--danger btn--sm"><?= icon('trash') ?> <?= e($text) ?></button>
<?php else: ?>
  <button type="submit" class="icon-btn icon-btn--delete" aria-label="<?= e($label) ?>" title="<?= e($label) ?>"><?= icon('trash') ?></button>
<?php endif; ?>
</form>
