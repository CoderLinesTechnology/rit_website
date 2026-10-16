<?php $this->layout('layouts/admin');
/** $items, $paginator, $counts, $programs, $filters */
$f = $filters;
$any = $f['status'] !== '' || $f['program'] > 0 || $f['q'] !== '' || $f['from'] !== '' || $f['to'] !== '';
$total = array_sum($counts);
?>
<?= $this->insert('admin/partials/page-header', ['title' => 'Training applications', 'subtitle' => 'Everyone who has applied to a training program.']) ?>

<nav class="pills" aria-label="Filter by status">
  <a class="pill<?= $f['status'] === '' ? ' is-current' : '' ?>" href="<?= e(url('/admin/applications')) ?>"<?= $f['status'] === '' ? ' aria-current="true"' : '' ?>>All <b><?= (int) $total ?></b></a>
<?php foreach ($counts as $status => $n): ?>
  <a class="pill<?= $f['status'] === $status ? ' is-current' : '' ?>" href="<?= e(url('/admin/applications', ['status' => $status])) ?>"<?= $f['status'] === $status ? ' aria-current="true"' : '' ?>><?= e(label($status)) ?> <b><?= (int) $n ?></b></a>
<?php endforeach; ?>
</nav>

<form class="filters" method="get" action="<?= e(url('/admin/applications')) ?>" role="search">
  <div class="field"><label class="field__label" for="f-q">Search</label><input class="input" type="search" id="f-q" name="q" value="<?= e($f['q']) ?>" maxlength="100" placeholder="Reference, name, email or phone"></div>
  <div class="field"><label class="field__label" for="f-status">Status</label>
    <select class="select" id="f-status" name="status"><option value="">All statuses</option>
<?php foreach ($counts as $status => $n): ?><option value="<?= e($status) ?>"<?= selected($f['status'] === $status) ?>><?= e(label($status)) ?></option>
<?php endforeach; ?></select></div>
  <div class="field"><label class="field__label" for="f-program">Program</label>
    <select class="select" id="f-program" name="program"><option value="">All programs</option>
<?php foreach ($programs as $p): ?><option value="<?= (int) $p['id'] ?>"<?= selected($f['program'] === (int) $p['id']) ?>><?= e($p['title']) ?></option>
<?php endforeach; ?></select></div>
  <div class="field"><label class="field__label" for="f-from">From</label><input class="input" type="date" id="f-from" name="from" value="<?= e($f['from']) ?>"></div>
  <div class="field"><label class="field__label" for="f-to">To</label><input class="input" type="date" id="f-to" name="to" value="<?= e($f['to']) ?>"></div>
  <div class="filters__actions"><button class="btn" type="submit"><?= icon('search') ?> Filter</button><?php if ($any): ?> <a class="btn btn--secondary" href="<?= e(url('/admin/applications')) ?>">Clear</a><?php endif; ?></div>
</form>

<?php if ($items === []): ?>
<?= $this->insert('admin/partials/empty-state', ['title' => $any ? 'No applications match those filters' : 'No applications yet', 'text' => $any ? 'Try removing a filter.' : 'Applications will appear here when people apply through the website.', 'icon' => 'file-earmark-text']) ?>
<?php else: ?>
<p class="muted result-count"><?= (int) $paginator->total ?> application<?= $paginator->total === 1 ? '' : 's' ?></p>
<div class="table-wrap"><table class="table">
  <caption class="visually-hidden">Training applications, newest first</caption>
  <thead><tr><th scope="col">Reference</th><th scope="col">Name</th><th scope="col">Program</th><th scope="col">Age</th><th scope="col">Submitted</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
  <tbody>
<?php foreach ($items as $a): ?>
    <tr>
      <td><?= e($a['reference']) ?></td>
      <th scope="row"><?= e($a['full_name']) ?></th>
      <td><?= e($a['program_title']) ?></td>
      <td><?= e(\App\Models\TrainingApplication::AGE_RANGES[$a['age_range']]['label'] ?? $a['age_range']) ?></td>
      <td><?= e(format_date($a['created_at'], 'j M Y, H:i')) ?></td>
      <td><?= $this->insert('admin/partials/badge', ['status' => $a['status']]) ?>
<?php if ((int) $a['duplicate_count'] > 0): ?> <span class="badge badge--duplicate" title="Another application from the same email for this program">Possible duplicate</span><?php endif; ?>
<?php if ((int) $a['has_attachment'] === 1): ?> <span class="attach"><?= icon('download', 'icon', 'Has an attachment') ?></span><?php endif; ?></td>
      <td class="table__actions"><a class="icon-btn icon-btn--view" href="<?= e(url('/admin/applications/' . (int) $a['id'])) ?>"><?= icon('eye') ?><span class="visually-hidden">View application <?= e($a['reference']) ?></span></a></td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table></div>
<?= $this->insert('partials/pagination', ['paginator' => $paginator]) ?>
<?php endif; ?>
