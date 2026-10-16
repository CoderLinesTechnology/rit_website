<?php $this->layout('layouts/admin');
/** $cfg, $row, $statuses */
$path = (string) $cfg['path']; $id = (int) $row['id'];
$dt = static fn (?string $v): string => $v === null || $v === '' ? '—' : format_date($v, 'j M Y, H:i');
?>
<?= $this->insert('admin/partials/page-header', ['title' => $cfg['singular'] . ' ' . $row['reference'], 'subtitle' => 'Received ' . $dt($row['created_at']), 'actions' => [['label' => 'Back to list', 'href' => $path, 'icon' => 'chevron-left', 'variant' => 'secondary']]]) ?>
<?= $this->insert('partials/error-summary') ?>

<div class="detail-layout">
  <div>
    <section class="card" aria-labelledby="h-details">
      <h2 id="h-details">Details</h2>
      <dl class="detail-list">
        <dt>Status</dt><dd><?= $this->insert('admin/partials/badge', ['status' => $row['status']]) ?></dd>
<?php foreach ($cfg['fields'] as $field):
    [$label, $key] = $field; $kind = $field[2] ?? 'text'; $map = $field[3] ?? null;
    $val = (string) ($row[$key] ?? '');
    if ($map !== null) { $val = $map[$val] ?? $val; }
?>
        <dt><?= e($label) ?></dt>
<?php if ($val === ''): ?>
        <dd>—</dd>
<?php elseif ($kind === 'email'): ?>
        <dd><?php if (mailto_url((string) $val) !== ''): ?><a href="<?= e(mailto_url((string) $val)) ?>"><?= e($val) ?></a><?php else: ?><?= e($val) ?><?php endif; ?></dd>
<?php elseif ($kind === 'url' && preg_match('#^https?://#i', $val) === 1): ?>
        <dd><a href="<?= e($val) ?>" rel="noopener noreferrer nofollow" target="_blank"><?= e($val) ?></a></dd>
<?php elseif ($kind === 'long'): ?>
        <dd class="prewrap"><?= e($val) ?></dd>
<?php else: ?>
        <dd><?= e($val) ?></dd>
<?php endif; ?>
<?php endforeach; ?>
      </dl>
<?php if (!empty($cfg['reply'])): ?>
      <p><a class="btn btn--sm" href="<?= e(mailto_url((string) $row['email'], 'Re: ' . $row['subject'] . ' [' . $row['reference'] . ']')) ?>"><?= icon('envelope') ?> Reply by email</a></p>
<?php endif; ?>
    </section>
  </div>

  <div>
    <section class="card" aria-labelledby="h-status">
      <h2 id="h-status">Status</h2>
      <form method="post" action="<?= e(url($path . '/' . $id . '/status')) ?>" class="form">
        <?= csrf_field() ?>
        <div class="field"><label class="field__label" for="status">Change status</label>
          <select class="select" id="status" name="status">
<?php foreach ($statuses as $s): ?><option value="<?= e($s) ?>"<?= selected($s === $row['status']) ?>><?= e(label($s)) ?></option>
<?php endforeach; ?></select></div>
        <div class="form__actions"><button class="btn btn--sm" type="submit">Update status</button></div>
      </form>
      <p class="muted small">Last changed: <?= e($dt($row['status_changed_at'])) ?></p>
    </section>

    <section class="card" aria-labelledby="h-notes">
      <h2 id="h-notes">Internal notes</h2>
      <form method="post" action="<?= e(url($path . '/' . $id . '/notes')) ?>" class="form">
        <?= csrf_field() ?>
        <?= $this->insert('partials/form/textarea', ['name' => 'admin_notes', 'label' => 'Notes (only staff see these)', 'value' => old('admin_notes', (string) ($row['admin_notes'] ?? '')), 'rows' => 6, 'maxlength' => 5000, 'counter' => true]) ?>
        <div class="form__actions"><button class="btn btn--sm" type="submit">Save notes</button></div>
      </form>
    </section>

    <section class="danger-zone" aria-labelledby="h-delete">
      <h2 id="h-delete">Delete</h2>
      <p>Use this when the person asks for their data to be erased, or when it is no longer needed.</p>
      <form method="post" action="<?= e(url($path . '/' . $id . '/delete')) ?>" data-confirm="This permanently deletes this record.">
        <?= csrf_field() ?>
        <button class="btn btn--danger btn--sm" type="submit"><?= icon('trash') ?> Delete permanently</button>
      </form>
    </section>
  </div>
</div>
