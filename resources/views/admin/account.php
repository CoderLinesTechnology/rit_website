<?php $this->layout('layouts/admin'); /** $me */ ?>
<?= $this->insert('admin/partials/page-header', ['title' => 'My account', 'subtitle' => 'Your sign-in details.']) ?>
<?= $this->insert('partials/error-summary') ?>
<div class="detail-layout">
  <section class="card" aria-labelledby="h-profile">
    <h2 id="h-profile">Your details</h2>
    <form method="post" action="<?= e(url('/admin/account')) ?>" class="form" data-once>
      <?= csrf_field() ?>
      <?= $this->insert('partials/form/input', ['name' => 'name', 'label' => 'Name', 'required' => true, 'maxlength' => 120, 'autocomplete' => 'name', 'value' => old('name', (string) $me['name'])]) ?>
      <div class="form__actions"><button class="btn btn--sm" type="submit">Save name</button></div>
    </form>
    <dl class="detail-list">
      <dt>Email</dt><dd><?= e($me['email']) ?></dd>
      <dt>Role</dt><dd><?= e(\App\Core\Auth::ROLES[$me['role']] ?? $me['role']) ?></dd>
      <dt>Last sign-in</dt><dd><?= e($me['last_login_at'] !== null ? format_date($me['last_login_at'], 'j M Y, H:i') : '—') ?></dd>
      <dt>Password changed</dt><dd><?= e($me['password_changed_at'] !== null ? format_date($me['password_changed_at'], 'j M Y') : 'Never') ?></dd>
    </dl>
    <p class="muted small">To change your email address or role, ask a super administrator.</p>
  </section>
  <section class="card" aria-labelledby="h-pw">
    <h2 id="h-pw">Change password</h2>
    <p class="muted">Other devices where you are signed in will be signed out.</p>
    <form method="post" action="<?= e(url('/admin/account/password')) ?>" class="form" data-once>
      <?= csrf_field() ?>
      <?= $this->insert('partials/form/input', ['name' => 'current_password', 'label' => 'Current password', 'type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
      <?= $this->insert('partials/form/input', ['name' => 'password', 'label' => 'New password', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'minlength' => 12, 'maxlength' => 72, 'hint' => 'At least 12 characters. A few random words make a strong passphrase.']) ?>
      <?= $this->insert('partials/form/input', ['name' => 'password_confirmation', 'label' => 'Confirm new password', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'maxlength' => 72]) ?>
      <div class="form__actions"><button class="btn btn--sm" type="submit">Change password</button></div>
    </form>
  </section>
</div>
