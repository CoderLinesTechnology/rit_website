<?php $this->layout('layouts/admin');
/** $cfg, $statuses, $counts, $items, $paginator, $filters */
$f = $filters; $path = (string) $cfg['path'];
$any = $f['status'] !== '' || $f['q'] !== '' || $f['from'] !== '' || $f['to'] !== '';
$total = array_sum($counts);
?>
<?= $this->insert('admin/partials/page-header', ['title' => $cfg['title']]) ?>

<nav class="pills" aria-label="Filter by status">
  <a class="pill<?= $f['status'] === '' ? ' is-current' : '' ?>" href="<?= e(url($path)) ?>"<?= $f['status'] === '' ? ' aria-current="true"' : '' ?>>All <b><?= (int) $total ?></b></a>
<?php foreach ($counts as $status => $n): ?>
  <a class="pill<?= $f['status'] === $status ? ' is-current' : '' ?>" href="<?= e(url($path, ['status' => $status])) ?>"<?= $f['status'] === $status ? ' aria-current="true"' : '' ?>><?= e(label($status)) ?> <b><?= (int) $n ?></b></a>
<?php endforeach; ?>
</nav>

<form class="filters" method="get" action="<?= e(url($path)) ?>" role="search">
  <div class="field"><label class="field__label" for="f-q">Search</label><input class="input" type="search" id="f-q" name="q" value="<?= e($f['q']) ?>" maxlength="100" placeholder="<?= e($cfg['search_hint']) ?>"></div>
  <div class="field"><label class="field__label" for="f-status">Status</label>
    <select class="select" id="f-status" name="status"><option value="">All statuses</option>
<?php foreach ($statuses as $s): ?><option value="<?= e($s) ?>"<?= selected($f['status'] === $s) ?>><?= e(label($s)) ?></option>
<?php endforeach; ?></select></div>
  <div class="field"><label class="field__label" for="f-from">From</label><input class="input" type="date" id="f-from" name="from" value="<?= e($f['from']) ?>"></div>
  <div class="field"><label class="field__label" for="f-to">To</label><input class="input" type="date" id="f-to" name="to" value="<?= e($f['to']) ?>"></div>
  <div class="filters__actions"><button class="btn" type="submit"><?= icon('search') ?> Filter</button><?php if ($any): ?> <a class="btn btn--secondary" href="<?= e(url($path)) ?>">Clear</a><?php endif; ?></div>
</form>

<?php if ($items === []): ?>
<?= $this->insert('admin/partials/empty-state', ['title' => $any ? 'Nothing matches those filters' : 'Nothing here yet', 'text' => $any ? 'Try removing a filter.' : 'New submissions from the website will appear here.', 'icon' => 'inboxes']) ?>
<?php else: ?>
<p class="muted result-count"><?= (int) $paginator->total ?> record<?= $paginator->total === 1 ? '' : 's' ?></p>
<div class="table-wrap"><table class="table">
  <caption class="visually-hidden"><?= e($cfg['title']) ?>, newest first</caption>
  <thead><tr><th scope="col">Reference</th>
<?php foreach ($cfg['columns'] as $col): ?><th scope="col"><?= e($col[0]) ?></th>
<?php endforeach; ?><th scope="col">Received</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
  <tbody>
<?php foreach ($items as $row): ?>
    <tr>
      <td><?= e($row['reference']) ?></td>
<?php foreach ($cfg['columns'] as $i => $col): $val = (string) ($row[$col[1]] ?? ''); if (isset($col[2])) { $val = $col[2][$val] ?? $val; } ?>
      <?= $i === 0 ? '<th scope="row">' : '<td>' ?><?= e($val !== '' ? $val : '—') ?><?= $i === 0 ? '</th>' : '</td>' ?>

<?php endforeach; ?>
      <td><?= e(format_date($row['created_at'], 'j M Y, H:i')) ?></td>
      <td><?= $this->insert('admin/partials/badge', ['status' => $row['status']]) ?></td>
      <td class="table__actions"><a class="icon-btn icon-btn--view" href="<?= e(url($path . '/' . (int) $row['id'])) ?>"><?= icon('eye') ?><span class="visually-hidden">View <?= e($row['reference']) ?></span></a></td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table></div>
<?= $this->insert('partials/pagination', ['paginator' => $paginator]) ?>
<?php endif; ?>
