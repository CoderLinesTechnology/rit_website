<?php
/** vars: $metrics */
$this->layout('layouts/admin');
?>
<?= $this->insert('admin/partials/page-header', [
    'title' => 'Impact metrics',
    'subtitle' => 'Headline figures shown on the Impact page and homepage. Only publish figures the Foundation has verified.',
    'actions' => [['label' => 'New metric', 'href' => '/admin/impact/create', 'icon' => 'plus-lg']],
]) ?>
<?php if (!$metrics): ?>
<?= $this->insert('admin/partials/empty-state', ['icon' => 'bar-chart', 'title' => 'No impact metrics yet', 'text' => 'Add a figure such as the number of young people reached.', 'actionLabel' => 'New metric', 'actionHref' => '/admin/impact/create']) ?>
<?php else: ?>
<div class="table-wrap">
  <table class="table">
    <caption class="visually-hidden">Impact metrics</caption>
    <thead><tr><th scope="col">Label</th><th scope="col">Figure</th><th scope="col">Period</th><th scope="col">Status</th><th scope="col">On homepage</th><th scope="col" class="num">Order</th><th scope="col" class="table__actions"><span class="visually-hidden">Actions</span></th></tr></thead>
    <tbody>
<?php foreach ($metrics as $m): $mid = (int) $m['id']; ?>
      <tr>
        <th scope="row"><a href="<?= e(url('/admin/impact/' . $mid . '/edit')) ?>"><?= e($m['label']) ?></a></th>
        <td><?= (int) $m['is_approximate'] === 1 ? '~ ' : '' ?><?= e($m['value']) ?></td>
        <td><?= e($m['reporting_period'] ?? '') ?: '<span class="muted">Not set</span>' ?></td>
        <td><?= $this->insert('admin/partials/badge', ['status' => $m['status']]) ?></td>
        <td><?= (int) $m['show_on_homepage'] === 1 ? 'Yes' : '<span class="muted">No</span>' ?></td>
        <td class="num"><?= (int) $m['sort_order'] ?></td>
        <td class="table__actions">
          <a class="icon-btn icon-btn--edit" href="<?= e(url('/admin/impact/' . $mid . '/edit')) ?>" aria-label="Edit <?= e($m['label']) ?>" title="Edit"><?= icon('pencil') ?></a>
<?= $this->insert('admin/partials/content-delete-form', ['action' => '/admin/impact/' . $mid . '/delete', 'confirm' => 'Delete the impact metric "' . $m['label'] . '"? This cannot be undone.', 'label' => 'Delete ' . $m['label']]) ?>
        </td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
