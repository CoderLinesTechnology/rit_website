<?php $this->layout('layouts/admin');
/** $app, $duplicates, $changedBy, $statuses */
$under18 = (int) $app['is_adult'] === 0;
$ageLabel = \App\Models\TrainingApplication::AGE_RANGES[$app['age_range']]['label'] ?? $app['age_range'];
$id = (int) $app['id'];
$dt = static fn (?string $v): string => $v === null || $v === '' ? '—' : format_date($v, 'j M Y, H:i');
?>
<?= $this->insert('admin/partials/page-header', ['title' => 'Application ' . $app['reference'], 'subtitle' => 'Submitted ' . $dt($app['created_at']), 'actions' => [['label' => 'Back to applications', 'href' => '/admin/applications', 'icon' => 'chevron-left', 'variant' => 'secondary']]]) ?>
<?= $this->insert('partials/error-summary') ?>

<div class="detail-layout">
  <div>
    <section class="card" aria-labelledby="h-applicant">
      <h2 id="h-applicant">Applicant</h2>
      <dl class="detail-list">
        <dt>Status</dt><dd><?= $this->insert('admin/partials/badge', ['status' => $app['status']]) ?></dd>
        <dt>Name</dt><dd><?= e($app['full_name']) ?></dd>
        <dt>Email</dt><dd><?php if (mailto_url($app['email']) !== ''): ?><a href="<?= e(mailto_url($app['email'])) ?>"><?= e($app['email']) ?></a><?php else: ?><?= e($app['email']) ?><?php endif; ?></dd>
        <dt>Phone</dt><dd><?= e($app['phone']) ?></dd>
        <dt>Age range</dt><dd><?= e($ageLabel) ?></dd>
        <dt>Region</dt><dd><?= e($app['location']) ?></dd>
        <dt>Program</dt><dd><?= e($app['program_title']) ?></dd>
        <dt>Availability</dt><dd><?= e($app['availability'] !== null && $app['availability'] !== '' ? implode(', ', array_map(static fn (string $k): string => \App\Models\TrainingApplication::AVAILABILITY[$k] ?? $k, array_map('trim', explode(',', (string) $app['availability'])))) : '—') ?></dd>
      </dl>
<?php if ($under18): ?>
      <div class="guardian guardian--<?= (int) $app['guardian_consent'] === 1 ? 'ok' : 'missing' ?>">
        <strong><?= (int) $app['guardian_consent'] === 1 ? 'Under 18 — guardian consent given' : 'Under 18 — guardian consent missing' ?></strong>
        <dl class="detail-list">
          <dt>Guardian</dt><dd><?= e($app['guardian_name'] ?? '—') ?></dd>
          <dt>Guardian phone</dt><dd><?= e($app['guardian_phone'] ?? '—') ?></dd>
        </dl>
      </div>
<?php endif; ?>
    </section>

    <section class="card" aria-labelledby="h-answers">
      <h2 id="h-answers">Their answers</h2>
      <h3>Why they want to join</h3>
      <p class="prewrap"><?= e($app['reason']) ?></p>
      <h3>Background and experience</h3>
      <p class="prewrap"><?= e($app['background'] !== null && $app['background'] !== '' ? $app['background'] : '—') ?></p>
      <h3>Attachment</h3>
<?php if (!empty($app['attachment_path'])): ?>
      <p><a class="btn btn--secondary btn--sm" href="<?= e(url('/admin/applications/' . $id . '/attachment')) ?>"><?= icon('download') ?> Download <?= e($app['attachment_name']) ?></a> <span class="muted">(<?= e(number_format(((int) $app['attachment_size']) / 1024, 0)) ?> KB)</span></p>
<?php else: ?>
      <p class="muted">No file was attached.</p>
<?php endif; ?>
    </section>
  </div>

  <div>
    <section class="card" aria-labelledby="h-status">
      <h2 id="h-status">Status</h2>
<?php $final = ['accepted', 'rejected']; $statusUrl = url('/admin/applications/' . $id . '/status'); ?>
      <form method="post" action="<?= e($statusUrl) ?>" class="form">
        <?= csrf_field() ?>
        <div class="field"><label class="field__label" for="status">Change status</label>
          <select class="select" id="status" name="status">
<?php if (in_array($app['status'], $final, true)): ?><option value="" disabled selected>Currently: <?= e(label($app['status'])) ?></option>
<?php endif; ?>
<?php foreach ($statuses as $s): if (in_array($s, $final, true)) { continue; } ?><option value="<?= e($s) ?>"<?= selected($s === $app['status']) ?>><?= e(label($s)) ?></option>
<?php endforeach; ?></select></div>
        <div class="form__actions"><button class="btn btn--sm" type="submit">Update status</button></div>
      </form>
      <div class="decision">
<?php foreach ($final as $s): if ($s === $app['status']) { continue; } ?>
        <form method="post" action="<?= e($statusUrl) ?>" data-confirm="<?= e('Mark this application as ' . $s . '? The applicant is not emailed automatically.') ?>">
          <?= csrf_field() ?><input type="hidden" name="status" value="<?= e($s) ?>">
          <button class="btn btn--sm<?= $s === 'rejected' ? ' btn--secondary' : '' ?>" type="submit"><?= $s === 'accepted' ? 'Accept' : 'Reject' ?></button>
        </form>
<?php endforeach; ?>
      </div>
      <p class="muted small">The applicant is not emailed automatically. Contact them yourself if needed.</p>
      <p class="muted small">Last changed: <?= e($dt($app['status_changed_at'])) ?><?= $changedBy !== null ? ' by ' . e($changedBy) : '' ?></p>
<?php if ($duplicates > 0): ?>
      <p class="notice">Possible duplicate: <?= (int) $duplicates ?> other application<?= $duplicates === 1 ? '' : 's' ?> from the same email address for this program. <a href="<?= e(url('/admin/applications', ['duplicates_of' => (int) $app['id']])) ?>">See them</a></p>
<?php endif; ?>
    </section>

    <section class="card" aria-labelledby="h-notes">
      <h2 id="h-notes">Internal notes</h2>
      <form method="post" action="<?= e(url('/admin/applications/' . $id . '/notes')) ?>" class="form">
        <?= csrf_field() ?>
        <?= $this->insert('partials/form/textarea', ['name' => 'admin_notes', 'label' => 'Notes (only staff see these)', 'value' => old('admin_notes', (string) ($app['admin_notes'] ?? '')), 'rows' => 6, 'maxlength' => 5000, 'counter' => true, 'hint' => 'Do not record sensitive details that are not needed.']) ?>
        <div class="form__actions"><button class="btn btn--sm" type="submit">Save notes</button></div>
      </form>
    </section>

    <section class="danger-zone" aria-labelledby="h-delete">
      <h2 id="h-delete">Delete application</h2>
      <p>Use this when the person asks for their data to be erased, or when it is no longer needed.</p>
      <form method="post" action="<?= e(url('/admin/applications/' . $id . '/delete')) ?>" data-confirm="This permanently deletes the application and its attachment.">
        <?= csrf_field() ?>
        <button class="btn btn--danger btn--sm" type="submit"><?= icon('trash') ?> Delete permanently</button>
      </form>
    </section>
  </div>
</div>
