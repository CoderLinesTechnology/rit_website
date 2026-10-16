<?php
/** vars: $categories, $protectedSlug */
$this->layout('layouts/admin');
?>
<?= $this->insert('admin/partials/page-header', [
    'title' => 'News categories',
    'subtitle' => 'Groups used to file news articles and success stories.',
    'actions' => [['label' => 'New category', 'href' => '/admin/news-categories/create', 'icon' => 'plus-lg']],
]) ?>
<?php if (!$categories): ?>
<?= $this->insert('admin/partials/empty-state', ['icon' => 'grid', 'title' => 'No categories yet', 'text' => 'Create a category, then file articles under it.', 'actionLabel' => 'New category', 'actionHref' => '/admin/news-categories/create']) ?>
<?php else: ?>
<div class="table-wrap">
  <table class="table">
    <caption class="visually-hidden">News categories</caption>
    <thead><tr><th scope="col">Name</th><th scope="col">Web address</th><th scope="col" class="num">Articles</th><th scope="col" class="num">Order</th><th scope="col" class="table__actions"><span class="visually-hidden">Actions</span></th></tr></thead>
    <tbody>
<?php foreach ($categories as $c): $cid = (int) $c['id']; $locked = $c['slug'] === $protectedSlug; ?>
      <tr>
        <th scope="row"><a href="<?= e(url('/admin/news-categories/' . $cid . '/edit')) ?>"><?= e($c['name']) ?></a><?php if ($locked): ?> <span class="badge">Used by the website</span><?php endif; ?></th>
        <td><code><?= e($c['slug']) ?></code></td>
        <td class="num"><?= (int) $c['article_count'] ?></td>
        <td class="num"><?= (int) $c['sort_order'] ?></td>
        <td class="table__actions">
          <a class="icon-btn icon-btn--edit" href="<?= e(url('/admin/news-categories/' . $cid . '/edit')) ?>" aria-label="Edit <?= e($c['name']) ?>" title="Edit"><?= icon('pencil') ?></a>
<?php if (!$locked): ?>
<?= $this->insert('admin/partials/content-delete-form', [
    'action' => '/admin/news-categories/' . $cid . '/delete',
    'confirm' => (int) $c['article_count'] > 0
        ? 'This category is still used by articles, so it cannot be deleted until they are moved or deleted. Try anyway?'
        : 'Delete the category "' . $c['name'] . '"? This cannot be undone.',
    'label' => 'Delete ' . $c['name'],
]) ?>
<?php endif; ?>
        </td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
