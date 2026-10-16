<?php
/** vars: $articles, $paginator, $filters, $categoryOptions, $returnTo */
$this->layout('layouts/admin');
$filtered = array_filter($filters, static fn ($x): bool => $x !== '');
?>
<?= $this->insert('admin/partials/page-header', [
    'title' => 'News & stories',
    'subtitle' => 'News articles and success stories. Drafts and scheduled articles are not visible to the public.',
    'actions' => [['label' => 'New article', 'href' => '/admin/news/create', 'icon' => 'plus-lg']],
]) ?>
<?= $this->insert('admin/partials/content-filter-bar', [
    'action' => '/admin/news', 'filters' => $filters, 'categories' => $categoryOptions,
    'statuses' => ['draft' => 'Draft', 'published' => 'Published'],
]) ?>
<?php if (!$articles): ?>
<?= $this->insert('admin/partials/empty-state', $filtered
    ? ['icon' => 'search', 'title' => 'No articles match those filters', 'text' => 'Try a different search or clear the filters.', 'actionLabel' => 'Clear filters', 'actionHref' => '/admin/news']
    : ['icon' => 'journal-text', 'title' => 'No articles yet', 'text' => 'Write your first news article or success story.', 'actionLabel' => 'New article', 'actionHref' => '/admin/news/create']) ?>
<?php else: ?>
<div class="table-wrap">
  <table class="table">
    <caption class="visually-hidden">Articles (<?= (int) $paginator->total ?> in total)</caption>
    <thead>
      <tr>
        <th scope="col">Title</th><th scope="col">Category</th><th scope="col">Status</th><th scope="col">Publish date</th>
        <th scope="col">Featured</th><th scope="col">Consent</th>
        <th scope="col" class="table__actions"><span class="visually-hidden">Actions</span></th>
      </tr>
    </thead>
    <tbody>
<?php foreach ($articles as $a): $aid = (int) $a['id']; ?>
      <tr>
        <th scope="row"><a href="<?= e(url('/admin/news/' . $aid . '/edit')) ?>"><?= e($a['title']) ?></a></th>
        <td><?= e($a['category_name'] ?? '') ?: '<span class="muted">None</span>' ?></td>
        <td><?= $this->insert('admin/partials/badge', ['status' => $a['is_scheduled'] ? 'scheduled' : $a['status']]) ?></td>
        <td><?= $a['published_at'] ? '<time datetime="' . e(iso_date($a['published_at'])) . '">' . e(format_date($a['published_at'], 'j M Y, H:i')) . '</time>' : '<span class="muted">Not set</span>' ?></td>
        <td><?= (int) $a['is_featured'] === 1 ? 'Yes' : '<span class="muted">No</span>' ?></td>
        <td><?php if ($a['is_success_story']): ?><?= (int) $a['consent_confirmed'] === 1 ? '<span class="content-yes">' . icon('check-circle') . ' Confirmed</span>' : '<span class="badge badge--pending">Needed</span>' ?><?php else: ?><span class="muted">n/a</span><?php endif; ?></td>
        <td class="table__actions">
<?php if ($a['status'] === 'published' && !$a['is_scheduled']): ?>
          <a class="icon-btn icon-btn--view" href="<?= e(url('/news/' . $a['slug'])) ?>" target="_blank" rel="noopener" aria-label="View <?= e($a['title']) ?> on the website (opens in a new tab)" title="View on website"><?= icon('eye') ?></a>
<?php endif; ?>
<?php if ($a['status'] === 'published'): ?>
          <form class="inline-form" method="post" action="<?= e(url('/admin/news/' . $aid . '/unpublish')) ?>" data-confirm="Unpublish this article? It will disappear from the website and become a draft." data-once><?= csrf_field() ?><input type="hidden" name="return_to" value="<?= e($returnTo) ?>"><button type="submit" class="btn btn--secondary btn--sm">Unpublish</button></form>
<?php else: ?>
          <form class="inline-form" method="post" action="<?= e(url('/admin/news/' . $aid . '/publish')) ?>" data-once><?= csrf_field() ?><input type="hidden" name="return_to" value="<?= e($returnTo) ?>"><button type="submit" class="btn btn--sm">Publish</button></form>
<?php endif; ?>
          <a class="icon-btn icon-btn--edit" href="<?= e(url('/admin/news/' . $aid . '/edit')) ?>" aria-label="Edit <?= e($a['title']) ?>" title="Edit"><?= icon('pencil') ?></a>
<?= $this->insert('admin/partials/content-delete-form', ['action' => '/admin/news/' . $aid . '/delete', 'confirm' => 'Delete the article "' . $a['title'] . '"? This cannot be undone.', 'label' => 'Delete ' . $a['title'], 'returnTo' => $returnTo]) ?>
        </td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
</div>
<?= $this->insert('partials/pagination', ['paginator' => $paginator]) ?>
<?php endif; ?>
