<?php
/** vars: $categories */
$this->layout('layouts/admin');
?>
<?= $this->insert('admin/partials/page-header', [
    'title' => 'Program categories',
    'subtitle' => 'Groups that programs are filed under on the Programs page.',
    'actions' => [['label' => 'New category', 'href' => '/admin/program-categories/create', 'icon' => 'plus-lg']],
]) ?>
<?php if (!$categories): ?>
<?= $this->insert('admin/partials/empty-state', ['icon' => 'grid', 'title' => 'No categories yet', 'text' => 'Create a category, then file programs under it.', 'actionLabel' => 'New category', 'actionHref' => '/admin/program-categories/create']) ?>
<?php else: ?>
<div class="table-wrap">
  <table class="table">
    <caption class="visually-hidden">Program categories</caption>
    <thead><tr><th scope="col">Name</th><th scope="col">Web address</th><th scope="col" class="num">Programs</th><th scope="col" class="num">Order</th><th scope="col" class="table__actions"><span class="visually-hidden">Actions</span></th></tr></thead>
    <tbody>
<?php foreach ($categories as $c): $cid = (int) $c['id']; ?>
      <tr>
        <th scope="row"><a href="<?= e(url('/admin/program-categories/' . $cid . '/edit')) ?>"><?= e($c['name']) ?></a></th>
        <td><code><?= e($c['slug']) ?></code></td>
        <td class="num"><?= (int) $c['program_count'] ?></td>
        <td class="num"><?= (int) $c['sort_order'] ?></td>
        <td class="table__actions">
          <a class="icon-btn icon-btn--edit" href="<?= e(url('/admin/program-categories/' . $cid . '/edit')) ?>" aria-label="Edit <?= e($c['name']) ?>" title="Edit"><?= icon('pencil') ?></a>
<?= $this->insert('admin/partials/content-delete-form', [
    'action' => '/admin/program-categories/' . $cid . '/delete',
    'confirm' => (int) $c['program_count'] > 0
        ? 'This category still has programs, so it cannot be deleted until they are moved or deleted. Try anyway?'
        : 'Delete the category "' . $c['name'] . '"? This cannot be undone.',
    'label' => 'Delete ' . $c['name'],
]) ?>
        </td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
