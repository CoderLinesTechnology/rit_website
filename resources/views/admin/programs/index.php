<?php
/** vars: $programs, $paginator, $filters, $categoryOptions, $returnTo */
$this->layout('layouts/admin');
$filtered = array_filter($filters, static fn ($x): bool => $x !== '');
?>
<?= $this->insert('admin/partials/page-header', [
    'title' => 'Programs',
    'subtitle' => 'Training programs shown on the website. Only published programs are visible to the public.',
    'actions' => [['label' => 'New program', 'href' => '/admin/programs/create', 'icon' => 'plus-lg']],
]) ?>
<?= $this->insert('admin/partials/content-filter-bar', [
    'action' => '/admin/programs', 'filters' => $filters, 'categories' => $categoryOptions,
    'statuses' => ['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'],
]) ?>
<?php if (!$programs): ?>
<?= $this->insert('admin/partials/empty-state', $filtered
    ? ['icon' => 'search', 'title' => 'No programs match those filters', 'text' => 'Try a different search or clear the filters.', 'actionLabel' => 'Clear filters', 'actionHref' => '/admin/programs']
    : ['icon' => 'mortarboard', 'title' => 'No programs yet', 'text' => 'Add your first program so it can appear on the website.', 'actionLabel' => 'New program', 'actionHref' => '/admin/programs/create']) ?>
<?php else: ?>
<div class="table-wrap">
  <table class="table">
    <caption class="visually-hidden">Programs (<?= (int) $paginator->total ?> in total)</caption>
    <thead>
      <tr>
        <th scope="col">Title</th>
        <th scope="col">Category</th>
        <th scope="col">Status</th>
        <th scope="col">Featured</th>
        <th scope="col">Applications</th>
        <th scope="col" class="num">Received</th>
        <th scope="col">Updated</th>
        <th scope="col" class="table__actions"><span class="visually-hidden">Actions</span></th>
      </tr>
    </thead>
    <tbody>
<?php foreach ($programs as $p): $pid = (int) $p['id']; ?>
      <tr>
        <th scope="row"><a href="<?= e(url('/admin/programs/' . $pid . '/edit')) ?>"><?= e($p['title']) ?></a></th>
        <td><?= e($p['category_name'] ?? '') ?: '<span class="muted">None</span>' ?></td>
        <td><?= $this->insert('admin/partials/badge', ['status' => $p['status']]) ?></td>
        <td><?= (int) $p['is_featured'] === 1 ? 'Yes' : '<span class="muted">No</span>' ?></td>
        <td>
<?php if ($p['applications_open']): ?>
          <?= $this->insert('admin/partials/badge', ['status' => 'open']) ?>
<?php elseif ((int) $p['accepts_applications'] === 1): ?>
          <span class="muted">Not open now</span>
<?php else: ?>
          <span class="muted">Closed</span>
<?php endif; ?>
<?php if ((int) $p['accepts_applications'] === 1 && ($p['application_opens_on'] || $p['application_closes_on'])): ?>
          <br><small class="muted"><?= e($p['application_opens_on'] ? format_date($p['application_opens_on'], 'j M Y') : 'Any time') ?> to <?= e($p['application_closes_on'] ? format_date($p['application_closes_on'], 'j M Y') : 'no end date') ?></small>
<?php endif; ?>
        </td>
        <td class="num"><?= (int) $p['application_count'] ?></td>
        <td><time datetime="<?= e(iso_date($p['updated_at'])) ?>"><?= e(format_date($p['updated_at'], 'j M Y')) ?></time></td>
        <td class="table__actions">
<?php if ($p['status'] === 'published'): ?>
          <a class="icon-btn icon-btn--view" href="<?= e(url('/programs/' . $p['slug'])) ?>" target="_blank" rel="noopener" aria-label="View <?= e($p['title']) ?> on the website (opens in a new tab)" title="View on website"><?= icon('eye') ?></a>
<?php endif; ?>
          <a class="icon-btn icon-btn--edit" href="<?= e(url('/admin/programs/' . $pid . '/edit')) ?>" aria-label="Edit <?= e($p['title']) ?>" title="Edit"><?= icon('pencil') ?></a>
<?= $this->insert('admin/partials/content-delete-form', [
    'action' => '/admin/programs/' . $pid . '/delete',
    'confirm' => 'Delete the program "' . $p['title'] . '"? Applications already received for it are kept (they will just no longer be linked to a program). Its images are deleted. This cannot be undone.',
    'label' => 'Delete ' . $p['title'], 'returnTo' => $returnTo,
]) ?>
        </td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
</div>
<?= $this->insert('partials/pagination', ['paginator' => $paginator]) ?>
<?php endif; ?>
