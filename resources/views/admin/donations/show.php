<?php $this->layout('layouts/admin'); /** $donation, $events */
$d = $donation; $anon = (int) $d['is_anonymous'] === 1;
$dt = static fn (?string $v): string => $v === null || $v === '' ? '—' : format_date($v, 'j M Y, H:i');
?>
<?= $this->insert('admin/partials/page-header', ['title' => 'Donation ' . $d['reference'], 'actions' => [['label' => 'Back to donations', 'href' => '/admin/donations', 'icon' => 'chevron-left', 'variant' => 'secondary']]]) ?>
<section class="card" aria-labelledby="h-don">
  <h2 id="h-don">Details</h2>
  <dl class="detail-list">
    <dt>Amount</dt><dd><?= e(money((int) $d['amount_minor'], (string) $d['currency'])) ?></dd>
    <dt>Status</dt><dd><?= $this->insert('admin/partials/badge', ['status' => $d['status']]) ?> <?= $d['verified_at'] !== null ? '<span class="muted">Verified ' . e($dt($d['verified_at'])) . '</span>' : '<span class="muted">Not verified with the provider</span>' ?></dd>
    <dt>Donor</dt><dd><?= $anon || (string) $d['donor_name'] === '' ? 'Anonymous' : e($d['donor_name']) ?></dd>
<?php if (!$anon && (string) $d['donor_email'] !== ''): ?>
    <dt>Donor email</dt><dd><?= e($d['donor_email']) ?></dd>
<?php endif; ?>
    <dt>Provider</dt><dd><?= e($d['provider']) ?></dd>
    <dt>Provider status</dt><dd><?= e($d['provider_status'] ?? '—') ?></dd>
    <dt>Created</dt><dd><?= e($dt($d['created_at'])) ?></dd>
  </dl>
</section>
<section class="card" aria-labelledby="h-ev">
  <h2 id="h-ev">Payment events</h2>
<?php if ($events === []): ?>
  <p class="muted">No payment notifications have been received for this donation.</p>
<?php else: ?>
  <div class="table-wrap table-wrap--flush"><table class="table">
    <thead><tr><th scope="col">Received</th><th scope="col">Event</th><th scope="col">Signature</th><th scope="col">Processed</th></tr></thead>
    <tbody>
<?php foreach ($events as $ev): ?>
      <tr><td><?= e($dt($ev['received_at'])) ?></td><td><?= e($ev['event_type']) ?></td><td><?= (int) $ev['signature_valid'] === 1 ? 'Valid' : 'Invalid' ?></td><td><?= e($dt($ev['processed_at'])) ?></td></tr>
<?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>
</section>
