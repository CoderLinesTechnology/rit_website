<?php
$kinds = ['success' => ['status', 'check-circle'], 'error' => ['alert', 'exclamation-triangle'], 'info' => ['status', 'info-circle']];
$items = [];
foreach ($kinds as $k => [$role, $ico]) {
    $m = flash($k);
    if ($m) { $items[] = [$k, $role, $ico, (string) $m]; }
}
if (!$items) { return; }
?>
<div class="flash-region container">
<?php foreach ($items as [$k, $role, $ico, $m]): ?>
  <div class="alert alert--<?= e($k) ?>" role="<?= e($role) ?>"><?= icon($ico) ?><p><?= e($m) ?></p></div>
<?php endforeach; ?>
</div>
