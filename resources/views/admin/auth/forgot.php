<?php $this->layout('layouts/auth'); ?>
<h1>Reset your password</h1>
<?php if (empty($enabled)): ?>
<p>Password resets need email to be configured on this website, and it is not set up yet. Please ask a super administrator to set a new password for you.</p>
<?php else: ?>
<p class="muted">Enter the email address of your administrator account. If it is active, we will email you a link to choose a new password.</p>
<form method="post" action="<?= e(url('/admin/forgot-password')) ?>" class="form" data-once>
  <?= csrf_field() ?>
  <?= $this->insert('partials/form/input', ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'required' => true, 'autocomplete' => 'username', 'maxlength' => 190, 'attrs' => ['autocapitalize' => 'none', 'spellcheck' => 'false']]) ?>
  <button class="btn" type="submit" data-busy-text="Sending…">Send reset link</button>
</form>
<?php endif; ?>
<p class="auth__links"><a href="<?= e(url('/admin/login')) ?>">Back to sign in</a></p>
