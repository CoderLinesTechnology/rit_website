<?php $this->layout('layouts/auth'); ?>
<h1>First-time setup</h1>
<?= $this->insert('partials/error-summary') ?>
<p class="muted">Create the first super administrator. You need the setup token from the server's <code>.env</code> file.</p>
<form method="post" action="<?= e(url('/setup')) ?>" class="form" data-once>
  <?= csrf_field() ?>
  <?= $this->insert('partials/form/input', ['name' => 'setup_token', 'label' => 'Setup token', 'type' => 'password', 'required' => true, 'autocomplete' => 'off']) ?>
  <?= $this->insert('partials/form/input', ['name' => 'name', 'label' => 'Your name', 'required' => true, 'autocomplete' => 'name', 'maxlength' => 120]) ?>
  <?= $this->insert('partials/form/input', ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'required' => true, 'autocomplete' => 'username', 'maxlength' => 190]) ?>
  <?= $this->insert('partials/form/input', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'minlength' => 12, 'maxlength' => 72, 'hint' => 'At least 12 characters.']) ?>
  <?= $this->insert('partials/form/input', ['name' => 'password_confirmation', 'label' => 'Confirm password', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'maxlength' => 72]) ?>
  <button class="btn" type="submit" data-busy-text="Creating…">Create administrator</button>
</form>
