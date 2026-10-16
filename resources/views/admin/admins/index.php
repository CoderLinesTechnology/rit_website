<?php $this->layout('layouts/admin'); /** $admins, $meId */ ?>
<?= $this->insert('admin/partials/page-header', ['title' => 'Administrators', 'subtitle' => 'People who can sign in to this dashboard.', 'actions' => [['label' => 'Add administrator', 'href' => '/admin/admins/create', 'icon' => 'plus-lg']]]) ?>
<div class="table-wrap"><table class="table">
  <caption class="visually-hidden">Administrator accounts</caption>
  <thead><tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Role</th><th scope="col">Status</th><th scope="col">Last sign-in</th><th scope="col">Created</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
  <tbody>
<?php foreach ($admins as $a): $self = (int) $a['id'] === $meId; ?>
    <tr>
      <th scope="row"><?= e($a['name']) ?><?= $self ? ' <span class="muted">(you)</span>' : '' ?></th>
      <td><?= e($a['email']) ?></td>
      <td><?= e(\App\Core\Auth::ROLES[$a['role']] ?? $a['role']) ?></td>
      <td><?= $this->insert('admin/partials/badge', ['status' => (int) $a['is_active'] === 1 ? 'active' : 'inactive']) ?></td>
      <td><?= e($a['last_login_at'] !== null ? format_date($a['last_login_at'], 'j M Y, H:i') : 'Never') ?></td>
      <td><?= e(format_date($a['created_at'], 'j M Y')) ?></td>
      <td class="table__actions">
        <a class="icon-btn icon-btn--edit" href="<?= e(url('/admin/admins/' . (int) $a['id'] . '/edit')) ?>"><?= icon('pencil') ?><span class="visually-hidden">Edit <?= e($a['name']) ?></span></a>
<?php if (!$self): $on = (int) $a['is_active'] === 1; ?>
        <form method="post" action="<?= e(url('/admin/admins/' . (int) $a['id'] . '/status')) ?>"<?= $on ? ' data-confirm="Deactivate this account? They will be signed out everywhere."' : '' ?>>
          <?= csrf_field() ?><input type="hidden" name="active" value="<?= $on ? '0' : '1' ?>">
          <button class="btn btn--sm btn--secondary" type="submit"><?= $on ? 'Deactivate' : 'Activate' ?><span class="visually-hidden"> <?= e($a['name']) ?></span></button>
        </form>
<?php endif; ?>
      </td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table></div>
