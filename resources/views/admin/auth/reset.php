<?php $this->layout('layouts/auth'); /** $state form|invalid|disabled, $token */ ?>
<?php if ($state === 'form'): ?>
<h1>Choose a new password</h1>
<?= $this->insert('partials/error-summary') ?>
<p class="muted">Use at least 12 characters. A few random words make a strong, memorable passphrase.</p>
<form method="post" action="<?= e(url('/admin/reset-password/' . $token)) ?>" class="form" data-once>
  <?= csrf_field() ?>
  <?= $this->insert('partials/form/input', ['name' => 'password', 'label' => 'New password', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'minlength' => 12, 'maxlength' => 72]) ?>
  <?= $this->insert('partials/form/input', ['name' => 'password_confirmation', 'label' => 'Confirm new password', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'maxlength' => 72]) ?>
  <button class="btn" type="submit" data-busy-text="Saving…">Change password</button>
</form>
<?php elseif ($state === 'disabled'): ?>
<h1>Password reset unavailable</h1>
<p>Password resets need email to be configured on this website, and it is not set up yet. Please ask a super administrator to set a new password for you.</p>
<p class="auth__links"><a href="<?= e(url('/admin/login')) ?>">Back to sign in</a></p>
<?php else: ?>
<h1>This link is invalid or has expired</h1>
<p>Reset links work once and expire after 60 minutes. You can request another one.</p>
<p><a class="btn" href="<?= e(url('/admin/forgot-password')) ?>">Request a new link</a></p>
<?php endif; ?>
