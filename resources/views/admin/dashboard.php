<?php $this->layout('layouts/admin'); ?>
<?= $this->insert('admin/partials/page-header', ['title' => 'Welcome back, ' . $firstName . '!', 'subtitle' => "Here's what's happening with RIT Foundation Ghana.", 'date' => $today]) ?>

<div class="stats">
<?php foreach ($tiles as $t): ?>
  <?= $this->insert('admin/partials/stat-tile', $t) ?>
<?php endforeach; ?>
</div>

<div class="dash">
  <div class="dash__main">
<?php if ($canSubmissions): ?>
    <section class="card" aria-labelledby="dash-recent">
      <div class="card-head"><?= icon('file-earmark-text') ?><h2 id="dash-recent">Recent Applications</h2><a href="<?= e(url('/admin/applications')) ?>">View All <?= icon('arrow-right') ?></a></div>
<?php if ($recentApplications === []): ?>
      <p class="muted">No applications yet. They will appear here when people apply.</p>
<?php else: ?>
      <div class="table-wrap table-wrap--flush"><table class="table">
        <caption class="visually-hidden">The five most recent training applications</caption>
        <thead><tr><th scope="col">Applicant</th><th scope="col">Program</th><th scope="col">Date</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
        <tbody>
<?php foreach ($recentApplications as $a): ?>
          <tr>
            <th scope="row"><?= e($a['full_name']) ?><span class="ref"><?= e($a['reference']) ?></span></th>
            <td><?= e($a['program_title']) ?></td>
            <td><?= e(format_date($a['created_at'], 'j M Y')) ?></td>
            <td><?= $this->insert('admin/partials/badge', ['status' => $a['status']]) ?></td>
            <td class="table__actions"><a class="icon-btn icon-btn--view" href="<?= e(url('/admin/applications/' . (int) $a['id'])) ?>"><?= icon('eye') ?><span class="visually-hidden">View application <?= e($a['reference']) ?></span></a></td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table></div>
<?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($programs !== []): ?>
    <section class="card" aria-labelledby="dash-programs">
      <div class="card-head"><?= icon('mortarboard') ?><h2 id="dash-programs">Programs Overview</h2><?php if ($canContent): ?><a href="<?= e(url('/admin/programs')) ?>">View All <?= icon('arrow-right') ?></a><?php endif; ?></div>
      <div class="table-wrap table-wrap--flush"><table class="table">
        <caption class="visually-hidden">Programs and how many applications each has received</caption>
        <thead><tr><th scope="col">Program</th><th scope="col">Status</th><th scope="col" class="num">Applications</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
        <tbody>
<?php foreach ($programs as $p): ?>
          <tr>
            <th scope="row"><?= e($p['title']) ?><?php if (!empty($p['category_name'])): ?><span class="ref"><?= e($p['category_name']) ?></span><?php endif; ?></th>
            <td><?= $this->insert('admin/partials/badge', ['status' => $p['status']]) ?></td>
            <td class="num"><?= (int) $p['application_count'] ?></td>
            <td class="table__actions"><div class="btn-row">
<?php if ($p['status'] === 'published'): ?><a class="icon-btn icon-btn--view" href="<?= e(url('/programs/' . $p['slug'])) ?>"><?= icon('eye') ?><span class="visually-hidden">View <?= e($p['title']) ?> on the website</span></a><?php endif; ?>
<?php if ($canContent): ?><a class="icon-btn icon-btn--edit" href="<?= e(url('/admin/programs/' . (int) $p['id'] . '/edit')) ?>"><?= icon('pencil') ?><span class="visually-hidden">Edit <?= e($p['title']) ?></span></a><?php endif; ?>
            </div></td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table></div>
    </section>
<?php endif; ?>
  </div>

<?php if ($canSubmissions): ?>
  <div class="dash__charts">
    <section class="card chart-card" aria-labelledby="dash-status">
      <div class="card-head"><?= icon('bullseye') ?><h2 id="dash-status">Application Status</h2></div>
<?php if ($total === 0): ?>
      <p class="muted">No applications yet, so there is nothing to chart.</p>
<?php else: ?>
      <div class="donut"><?= $donut ?></div>
      <ul class="legend">
<?php foreach ($slices as $s): ?>
        <li><span class="legend__key" data-status="<?= e($s['status']) ?>"><svg viewBox="0 0 10 10" width="10" height="10" aria-hidden="true" focusable="false"><rect width="10" height="10" rx="2" fill="<?= e($s['color']) ?>"/></svg></span><span class="legend__label"><?= e($s['label']) ?></span><span class="legend__n"><?= (int) $s['value'] ?></span><span class="legend__pct"><?= $total > 0 ? (int) round($s['value'] * 100 / $total) : 0 ?>%</span></li>
<?php endforeach; ?>
      </ul>
      <div class="visually-hidden"><table><caption>Applications by status</caption><thead><tr><th scope="col">Status</th><th scope="col">Applications</th><th scope="col">Share</th></tr></thead><tbody>
<?php foreach ($slices as $s): ?><tr><th scope="row"><?= e($s['label']) ?></th><td><?= (int) $s['value'] ?></td><td><?= (int) round($s['value'] * 100 / $total) ?>%</td></tr>
<?php endforeach; ?></tbody></table></div>
<?php endif; ?>
    </section>

    <section class="card chart-card" aria-labelledby="dash-monthly">
      <div class="card-head"><?= icon('bar-chart') ?><h2 id="dash-monthly">Monthly Applications</h2><span class="muted card-head__note"><?= (int) $year ?></span></div>
      <?= $bars ?>
      <div class="visually-hidden"><table><caption>Applications received per month in <?= (int) $year ?></caption><thead><tr><th scope="col">Month</th><th scope="col">Applications</th></tr></thead><tbody>
<?php foreach ($months as $i => $m): ?><tr><th scope="row"><?= e($m) ?></th><td><?= (int) $monthValues[$i] ?></td></tr>
<?php endforeach; ?></tbody></table></div>
    </section>
  </div>
<?php endif; ?>

  <div class="dash__rail">
<?php
$actions = [];
if ($canContent) { $actions[] = ['/admin/programs/create', 'Add New Program', 'plus-lg']; $actions[] = ['/admin/news/create', 'Publish News / Story', 'newspaper']; }
if ($canSubmissions) { $actions[] = ['/admin/applications', 'View Applications', 'file-earmark-text']; }
if ($canContent) { $actions[] = ['/admin/gallery', 'Manage Gallery', 'images']; }
?>
<?php if ($actions): ?>
    <section class="card" aria-labelledby="dash-quick">
      <div class="card-head"><?= icon('lightbulb') ?><h2 id="dash-quick">Quick Actions</h2></div>
      <div class="quick-actions">
<?php foreach ($actions as [$href, $label, $ico]): ?>
        <a href="<?= e(url($href)) ?>"><?= icon($ico) ?><?= e($label) ?></a>
<?php endforeach; ?>
      </div>
    </section>
<?php endif; ?>

<?php if ($checklist !== null): ?>
    <section class="card" aria-labelledby="dash-check">
      <div class="card-head"><?= icon('check-circle') ?><h2 id="dash-check">Setup checklist</h2></div>
<?php if ($checklist === []): ?>
      <p class="muted">Everything on the checklist is in place.</p>
<?php else: ?>
      <ul class="checklist">
<?php foreach ($checklist as $item): ?>
        <li class="checklist__item checklist__item--<?= e($item['level']) ?>"><?= icon($item['level'] === 'warn' ? 'exclamation-triangle' : 'dash-lg') ?><span><?php if ($item['href'] !== null): ?><a href="<?= e(url($item['href'])) ?>"><?= e($item['text']) ?></a><?php else: ?><?= e($item['text']) ?><?php endif; ?></span></li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
    </section>
<?php endif; ?>

    <section class="card" aria-labelledby="dash-activity">
      <div class="card-head"><?= icon('clock-history') ?><h2 id="dash-activity">Recent Activity</h2></div>
<?php if ($activity === []): ?>
      <p class="muted">Nothing has happened yet.</p>
<?php else: ?>
      <ul class="activity">
<?php foreach ($activity as $a): ?>
        <li><span class="activity__dot activity__dot--<?= e($a['tone']) ?>"><?= icon($a['icon']) ?></span><div><strong><?= e($a['text']) ?></strong><?php if ($a['summary'] !== ''): ?><span><?= e($a['summary']) ?></span><?php endif; ?><time><?= e($a['when']) ?></time></div></li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
    </section>
  </div>
</div>
