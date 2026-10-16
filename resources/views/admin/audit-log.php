<?php $this->layout('layouts/admin'); /** $items, $paginator, $prefixes, $action, $admins, $adminFilter */
$entityLabels = ['training_application' => 'Application', 'volunteer_application' => 'Volunteer', 'partnership_enquiry' => 'Partnership', 'contact_enquiry' => 'Message', 'admin' => 'Administrator', 'program' => 'Program', 'news_article' => 'News article', 'gallery_image' => 'Photo', 'gallery_category' => 'Album'];
?>
<?= $this->insert('admin/partials/page-header', ['title' => 'Audit log', 'subtitle' => 'A record of who did what. It never contains passwords or applicants’ personal details.']) ?>
<form class="filters" method="get" action="<?= e(url('/admin/audit-log')) ?>">
  <div class="field"><label class="field__label" for="f-action">Type of action</label>
    <select class="select" id="f-action" name="action"><option value="">All actions</option>
<?php foreach ($prefixes as $p => $pl): ?><option value="<?= e($p) ?>"<?= selected($action === $p) ?>><?= e($pl) ?></option>
<?php endforeach; ?></select></div>
  <div class="field"><label class="field__label" for="f-admin">Administrator</label>
    <select class="select" id="f-admin" name="admin"><option value="">Everyone</option>
<?php foreach ($admins as $a): ?><option value="<?= (int) $a['id'] ?>"<?= selected($adminFilter === (int) $a['id']) ?>><?= e($a['name']) ?></option>
<?php endforeach; ?></select></div>
  <div class="filters__actions"><button class="btn" type="submit"><?= icon('search') ?> Filter</button></div>
</form>
<?php if ($items === []): ?>
<?= $this->insert('admin/partials/empty-state', ['title' => 'No entries match', 'icon' => 'clock-history']) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
  <caption class="visually-hidden">Audit log, newest first</caption>
  <thead><tr><th scope="col">Time</th><th scope="col">Administrator</th><th scope="col">Action</th><th scope="col">Item</th><th scope="col">Summary</th><th scope="col">IP address</th></tr></thead>
  <tbody>
<?php foreach ($items as $l): ?>
    <tr>
      <td class="nowrap"><?= e(format_date($l['created_at'], 'j M Y, H:i:s')) ?></td>
      <td><?= e($l['admin_name'] ?? '—') ?></td>
      <td><code><?= e($l['action']) ?></code></td>
      <td><?= e($l['entity_type'] !== null ? ($entityLabels[$l['entity_type']] ?? label((string) $l['entity_type'])) : '—') ?></td>
      <td><?= e($l['summary']) ?></td>
      <td><?= e($l['ip_address'] ?? '—') ?></td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table></div>
<?= $this->insert('partials/pagination', ['paginator' => $paginator]) ?>
<?php endif; ?>
