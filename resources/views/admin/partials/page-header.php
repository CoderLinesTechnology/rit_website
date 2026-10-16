<?php /** $title, ?$subtitle, ?$date, ?$actions [['label','href','icon','variant']] */ ?>
<div class="page-head">
  <div>
    <h1><?= e($title) ?></h1>
<?php if (!empty($subtitle)): ?><p class="page-head__sub"><?= e($subtitle) ?></p><?php endif; ?>
  </div>
<?php if (!empty($date)): ?><span class="page-head__date"><?= icon('calendar3') ?> <?= e($date) ?></span><?php endif; ?>
<?php if (!empty($actions)): ?>
  <div class="page-head__actions">
<?php foreach ($actions as $a): ?>
    <a class="btn<?= ($a['variant'] ?? '') === 'secondary' ? ' btn--secondary' : (($a['variant'] ?? '') === 'danger' ? ' btn--danger' : '') ?>" href="<?= e(url($a['href'])) ?>"><?php if (!empty($a['icon'])): ?><?= icon($a['icon']) ?> <?php endif; ?><?= e($a['label']) ?></a>
<?php endforeach; ?>
  </div>
<?php endif; ?>
</div>
