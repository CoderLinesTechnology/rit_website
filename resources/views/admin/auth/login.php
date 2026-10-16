<?php $this->layout('layouts/auth'); ?>
<h1>Sign in</h1>
<?php if (!empty($error)): ?>
<div class="alert alert--error" role="alert"><?= icon('exclamation-triangle') ?><p><?= e($error) ?></p></div>
<?php endif; ?>
<?php if (!empty($sessionNote)): ?>
<div class="alert alert--info" role="status"><?= icon('info-circle') ?><p><?= e($sessionNote) ?></p></div>
<?php endif; ?>
<form method="post" action="<?= e(url('/admin/login')) ?>" class="form" data-once>
  <?= csrf_field() ?>
  <?= $this->insert('partials/form/input', ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'value' => $email ?? '', 'required' => true, 'autocomplete' => 'username', 'maxlength' => 190, 'attrs' => ['autofocus' => 'autofocus', 'autocapitalize' => 'none', 'spellcheck' => 'false']]) ?>
  <?= $this->insert('partials/form/input', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
  <button class="btn" type="submit" data-busy-text="Signing in…">Sign in</button>
</form>
<?php if (!empty($canReset)): ?>
<p class="auth__links"><a href="<?= e(url('/admin/forgot-password')) ?>">Forgot your password?</a></p>
<?php endif; ?>
<p class="auth__back"><a href="<?= e(url('/')) ?>">&larr; Back to the website</a></p>
