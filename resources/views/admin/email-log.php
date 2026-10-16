<?php $this->layout('layouts/admin'); /** $items, $paginator, $counts, $status, $driver */
$dt = static fn (?string $v): string => $v === null || $v === '' ? '—' : format_date($v, 'j M Y, H:i');
?>
<?= $this->insert('admin/partials/page-header', ['title' => 'Email log', 'subtitle' => 'Every email the website tried to send. Message contents are not kept once sent.']) ?>
<?php if (in_array($driver, ['none', 'log'], true)): ?>
<div class="alert alert--error" role="alert"><?= icon('exclamation-triangle') ?><p><?= $driver === 'none' ? 'Email is not configured (driver: none). No email can be sent, so messages are recorded as skipped.' : 'The email driver is “log”, which is for development only. Messages are written to a file on the server and are not delivered.' ?></p></div>
<?php endif; ?>
<div class="card note-box">
  <p><strong>Handed off</strong> = accepted by the mail server; it does not prove the message reached the inbox. Failed messages are retried by <code>scripts/process-email-queue.php</code> (cron) up to 3 times.</p>
  <p class="muted">Current email driver: <strong><?= e($driver) ?></strong></p>
</div>
<nav class="pills" aria-label="Filter by status">
  <a class="pill<?= $status === '' ? ' is-current' : '' ?>" href="<?= e(url('/admin/email-log')) ?>">All <b><?= (int) array_sum($counts) ?></b></a>
<?php foreach ($counts as $s => $n): ?>
  <a class="pill<?= $status === $s ? ' is-current' : '' ?>" href="<?= e(url('/admin/email-log', ['status' => $s])) ?>"><?= e(label($s)) ?> <b><?= (int) $n ?></b></a>
<?php endforeach; ?>
</nav>
<?php if ($items === []): ?>
<?= $this->insert('admin/partials/empty-state', ['title' => 'No emails recorded', 'icon' => 'envelope']) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
  <caption class="visually-hidden">Email log, newest first</caption>
  <thead><tr><th scope="col">Created</th><th scope="col">Recipient</th><th scope="col">Purpose</th><th scope="col">Subject</th><th scope="col">Status</th><th scope="col" class="num">Attempts</th><th scope="col">Last error</th><th scope="col">Handed off</th></tr></thead>
  <tbody>
<?php foreach ($items as $m): ?>
    <tr>
      <td class="nowrap"><?= e($dt($m['created_at'])) ?></td>
      <td><?= e(str_contains((string) $m['recipient'], '@') ? \App\Core\Str::maskEmail((string) $m['recipient']) : $m['recipient']) ?></td>
      <td><code><?= e($m['purpose']) ?></code></td>
      <td><?= e($m['subject']) ?></td>
      <td><?= $this->insert('admin/partials/badge', ['status' => $m['status']]) ?></td>
      <td class="num"><?= (int) $m['attempts'] ?></td>
      <td><?= e($m['last_error'] ?? '—') ?></td>
      <td class="nowrap"><?= e($dt($m['handed_off_at'])) ?></td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table></div>
<?= $this->insert('partials/pagination', ['paginator' => $paginator]) ?>
<?php endif; ?>
