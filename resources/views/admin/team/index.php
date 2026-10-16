<?php
/** vars: $members, $milestones */
$this->layout('layouts/admin');
?>
<?= $this->insert('admin/partials/page-header', [
    'title' => 'Team & milestones',
    'subtitle' => 'People and key dates shown on the About page. Only published and approved entries are visible to the public.',
]) ?>

<section aria-labelledby="sec-team">
  <div class="section-head">
    <h2 id="sec-team">Team members</h2>
    <a class="btn btn--sm" href="<?= e(url('/admin/team/members/create')) ?>"><?= icon('plus-lg') ?> New team member</a>
  </div>
<?php if (!$members): ?>
<?= $this->insert('admin/partials/empty-state', ['icon' => 'people', 'title' => 'No team members yet', 'text' => 'Add the people who lead and run the Foundation.', 'actionLabel' => 'New team member', 'actionHref' => '/admin/team/members/create']) ?>
<?php else: ?>
  <div class="table-wrap">
    <table class="table">
      <caption class="visually-hidden">Team members</caption>
      <thead><tr><th scope="col">Photo</th><th scope="col">Name</th><th scope="col">Role</th><th scope="col">Status</th><th scope="col">Approved</th><th scope="col" class="num">Order</th><th scope="col" class="table__actions"><span class="visually-hidden">Actions</span></th></tr></thead>
      <tbody>
<?php foreach ($members as $m): $mid = (int) $m['id']; ?>
        <tr>
          <td><?php if (upload_url((string) $m['photo_thumb_path']) !== ''): ?><img class="table-thumb" src="<?= e(upload_url($m['photo_thumb_path'])) ?>" alt="" width="56" height="56" loading="lazy"><?php else: ?><span class="muted">None</span><?php endif; ?></td>
          <th scope="row"><a href="<?= e(url('/admin/team/members/' . $mid . '/edit')) ?>"><?= e($m['full_name']) ?></a></th>
          <td><?= e($m['role_title']) ?></td>
          <td><?= $this->insert('admin/partials/badge', ['status' => (int) $m['is_published'] === 1 ? 'published' : 'draft']) ?></td>
          <td><?= (int) $m['consent_confirmed'] === 1 ? 'Yes' : '<span class="badge badge--pending">Needed</span>' ?></td>
          <td class="num"><?= (int) $m['sort_order'] ?></td>
          <td class="table__actions">
            <a class="icon-btn icon-btn--edit" href="<?= e(url('/admin/team/members/' . $mid . '/edit')) ?>" aria-label="Edit <?= e($m['full_name']) ?>" title="Edit"><?= icon('pencil') ?></a>
<?= $this->insert('admin/partials/content-delete-form', ['action' => '/admin/team/members/' . $mid . '/delete', 'confirm' => 'Delete ' . $m['full_name'] . ' from the team? Their photo is deleted too. This cannot be undone.', 'label' => 'Delete ' . $m['full_name']]) ?>
          </td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
</section>

<section aria-labelledby="milestones">
  <div class="section-head">
    <h2 id="milestones">Milestones</h2>
    <a class="btn btn--sm" href="<?= e(url('/admin/team/milestones/create')) ?>"><?= icon('plus-lg') ?> New milestone</a>
  </div>
<?php if (!$milestones): ?>
<?= $this->insert('admin/partials/empty-state', ['icon' => 'clock-history', 'title' => 'No milestones yet', 'text' => 'Add verified key moments in the Foundation\'s journey.', 'actionLabel' => 'New milestone', 'actionHref' => '/admin/team/milestones/create']) ?>
<?php else: ?>
  <div class="table-wrap">
    <table class="table">
      <caption class="visually-hidden">Milestones</caption>
      <thead><tr><th scope="col">Date or period</th><th scope="col">Milestone</th><th scope="col">Verified</th><th scope="col">Status</th><th scope="col" class="num">Order</th><th scope="col" class="table__actions"><span class="visually-hidden">Actions</span></th></tr></thead>
      <tbody>
<?php foreach ($milestones as $m): $mid = (int) $m['id']; ?>
        <tr>
          <td><?= e($m['period_label']) ?></td>
          <th scope="row"><a href="<?= e(url('/admin/team/milestones/' . $mid . '/edit')) ?>"><?= e($m['title']) ?></a></th>
          <td><?= (int) $m['is_verified'] === 1 ? 'Yes' : '<span class="badge badge--pending">Not yet</span>' ?></td>
          <td><?= $this->insert('admin/partials/badge', ['status' => (int) $m['is_published'] === 1 ? 'published' : 'draft']) ?></td>
          <td class="num"><?= (int) $m['sort_order'] ?></td>
          <td class="table__actions">
            <a class="icon-btn icon-btn--edit" href="<?= e(url('/admin/team/milestones/' . $mid . '/edit')) ?>" aria-label="Edit milestone <?= e($m['title']) ?>" title="Edit"><?= icon('pencil') ?></a>
<?= $this->insert('admin/partials/content-delete-form', ['action' => '/admin/team/milestones/' . $mid . '/delete', 'confirm' => 'Delete the milestone "' . $m['title'] . '"? This cannot be undone.', 'label' => 'Delete milestone ' . $m['title']]) ?>
          </td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
</section>
