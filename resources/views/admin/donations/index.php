<?php $this->layout('layouts/admin'); /** $items, $paginator, $totals, $configured, $statuses, $status */ ?>
<?= $this->insert('admin/partials/page-header', ['title' => 'Donations', 'subtitle' => 'Records of online donations. Only payments verified with the provider count as received.']) ?>
<?php if (!$configured): ?>
<div class="alert alert--info" role="status"><?= icon('info-circle') ?><p>Online donations are not configured yet — no payments can be received through the website. Records will appear here once a payment provider is set up.</p></div>
<?php endif; ?>

<div class="stats stats--two">
  <?= $this->insert('admin/partials/stat-tile', ['label' => 'Verified donations', 'value' => $totals['count'], 'caption' => 'Confirmed with the payment provider', 'icon' => 'heart-fill', 'variant' => 'green']) ?>
  <?= $this->insert('admin/partials/stat-tile', ['label' => 'Verified total', 'value' => money($totals['total_minor']), 'caption' => 'Successful, verified payments only', 'icon' => 'cash-coin', 'variant' => 'maroon']) ?>
</div>

<form class="filters" method="get" action="<?= e(url('/admin/donations')) ?>">
  <div class="field"><label class="field__label" for="f-status">Status</label>
    <select class="select" id="f-status" name="status"><option value="">All statuses</option>
<?php foreach ($statuses as $s): ?><option value="<?= e($s) ?>"<?= selected($status === $s) ?>><?= e(label($s)) ?></option>
<?php endforeach; ?></select></div>
  <div class="filters__actions"><button class="btn" type="submit"><?= icon('search') ?> Filter</button></div>
</form>

<?php if ($items === []): ?>
<?= $this->insert('admin/partials/empty-state', ['title' => 'No donations recorded', 'icon' => 'cash-coin']) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
  <caption class="visually-hidden">Donations, newest first</caption>
  <thead><tr><th scope="col">Reference</th><th scope="col">Date</th><th scope="col" class="num">Amount</th><th scope="col">Donor</th><th scope="col">Provider</th><th scope="col">Status</th><th scope="col">Verified</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
  <tbody>
<?php foreach ($items as $d): ?>
    <tr>
      <td><?= e($d['reference']) ?></td>
      <td><?= e(format_date($d['created_at'], 'j M Y, H:i')) ?></td>
      <td class="num"><?= e(money((int) $d['amount_minor'], (string) $d['currency'])) ?></td>
      <td><?= (int) $d['is_anonymous'] === 1 || (string) $d['donor_name'] === '' ? 'Anonymous' : e($d['donor_name']) ?></td>
      <td><?= e($d['provider']) ?></td>
      <td><?= $this->insert('admin/partials/badge', ['status' => $d['status']]) ?></td>
      <td><?= $d['verified_at'] !== null ? icon('check-circle', 'icon icon--ok', 'Verified') : '<span aria-label="Not verified">—</span>' ?></td>
      <td class="table__actions"><a class="icon-btn icon-btn--view" href="<?= e(url('/admin/donations/' . (int) $d['id'])) ?>"><?= icon('eye') ?><span class="visually-hidden">View donation <?= e($d['reference']) ?></span></a></td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table></div>
<?= $this->insert('partials/pagination', ['paginator' => $paginator]) ?>
<?php endif; ?>
