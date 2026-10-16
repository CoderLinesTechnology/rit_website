<?php
/** vars: $albums */
$this->layout('layouts/admin');
?>
<?= $this->insert('admin/partials/page-header', [
    'title' => 'Gallery',
    'subtitle' => 'Photo albums. Photos are only shown publicly when the album is published, the photo is published and permission to publish it is confirmed.',
    'actions' => [['label' => 'New album', 'href' => '/admin/gallery/albums/create', 'icon' => 'plus-lg']],
]) ?>
<?php if (!$albums): ?>
<?= $this->insert('admin/partials/empty-state', ['icon' => 'images', 'title' => 'No albums yet', 'text' => 'Create an album, then upload photos to it.', 'actionLabel' => 'New album', 'actionHref' => '/admin/gallery/albums/create']) ?>
<?php else: ?>
<div class="table-wrap">
  <table class="table">
    <caption class="visually-hidden">Photo albums</caption>
    <thead><tr><th scope="col">Album</th><th scope="col">Event date</th><th scope="col" class="num">Photos</th><th scope="col">Status</th><th scope="col" class="num">Order</th><th scope="col" class="table__actions"><span class="visually-hidden">Actions</span></th></tr></thead>
    <tbody>
<?php foreach ($albums as $a): $aid = (int) $a['id']; ?>
      <tr>
        <th scope="row"><a href="<?= e(url('/admin/gallery/albums/' . $aid)) ?>"><?= e($a['name']) ?></a></th>
        <td><?= $a['event_date'] ? e(format_date($a['event_date'], 'j M Y')) : '<span class="muted">Not set</span>' ?></td>
        <td class="num"><?= (int) $a['image_count'] ?></td>
        <td><?= $this->insert('admin/partials/badge', ['status' => (int) $a['is_published'] === 1 ? 'published' : 'draft']) ?></td>
        <td class="num"><?= (int) $a['sort_order'] ?></td>
        <td class="table__actions">
<?php if ((int) $a['is_published'] === 1): ?>
          <a class="icon-btn icon-btn--view" href="<?= e(url('/gallery/' . $a['slug'])) ?>" target="_blank" rel="noopener" aria-label="View album <?= e($a['name']) ?> on the website (opens in a new tab)" title="View on website"><?= icon('eye') ?></a>
<?php endif; ?>
          <a class="icon-btn icon-btn--edit" href="<?= e(url('/admin/gallery/albums/' . $aid . '/edit')) ?>" aria-label="Edit album <?= e($a['name']) ?>" title="Edit album"><?= icon('pencil') ?></a>
<?= $this->insert('admin/partials/content-delete-form', [
    'action' => '/admin/gallery/albums/' . $aid . '/delete',
    'confirm' => (int) $a['image_count'] > 0
        ? 'This album still has photos, so it cannot be deleted until you delete them or move them to another album. Try anyway?'
        : 'Delete the album "' . $a['name'] . '"? This cannot be undone.',
    'label' => 'Delete album ' . $a['name'],
]) ?>
        </td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
