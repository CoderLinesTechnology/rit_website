<?php $this->layout('layouts/admin'); /** $admin|null, $roles, $descriptions, $isSelf */
$edit = $admin !== null; $id = $edit ? (int) $admin['id'] : 0;
?>
<?= $this->insert('admin/partials/page-header', ['title' => $edit ? 'Edit administrator' : 'Add administrator', 'actions' => [['label' => 'Back to administrators', 'href' => '/admin/admins', 'icon' => 'chevron-left', 'variant' => 'secondary']]]) ?>
<?= $this->insert('partials/error-summary') ?>

<section class="card" aria-labelledby="h-acct">
  <h2 id="h-acct">Account</h2>
  <form method="post" action="<?= e(url($edit ? '/admin/admins/' . $id : '/admin/admins')) ?>" class="form" data-once>
    <?= csrf_field() ?>
    <?= $this->insert('partials/form/input', ['name' => 'name', 'label' => 'Name', 'required' => true, 'maxlength' => 120, 'value' => old('name', (string) ($admin['name'] ?? ''))]) ?>
    <?= $this->insert('partials/form/input', ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'required' => true, 'maxlength' => 190, 'value' => old('email', (string) ($admin['email'] ?? ''))]) ?>
<?php if ($isSelf): ?>
    <?= $this->insert('partials/form/input', ['name' => 'current_password', 'label' => 'Current password', 'type' => 'password', 'required' => false, 'autocomplete' => 'current-password', 'maxlength' => 72, 'value' => '', 'hint' => 'Only needed if you change your email address.']) ?>
    <p class="muted">Your role is <strong><?= e($roles[$admin['role']] ?? '') ?></strong>. You cannot change your own role.</p>
<?php else: ?>
    <fieldset class="field<?= field_error('role') ? ' field--invalid' : '' ?>">
      <legend class="field__label">Role <span class="field__req" aria-hidden="true">*</span><span class="visually-hidden">(required)</span></legend>
      <div class="radios">
<?php $current = old('role', (string) ($admin['role'] ?? '')); foreach ($roles as $key => $roleLabel): ?>
        <label class="radio"><input type="radio" name="role" value="<?= e($key) ?>"<?= checked($current === $key) ?> required><span><strong><?= e($roleLabel) ?></strong><br><span class="muted"><?= e($descriptions[$key] ?? '') ?></span></span></label>
<?php endforeach; ?>
      </div>
<?php if (field_error('role')): ?><p class="field__error" id="field-role-error"><?= e(field_error('role')) ?></p><?php endif; ?>
    </fieldset>
<?php endif; ?>
<?php if (!$edit): ?>
    <?= $this->insert('partials/form/input', ['name' => 'password', 'label' => 'Initial password', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'minlength' => 12, 'maxlength' => 72, 'hint' => 'At least 12 characters. Share it securely (not by email) and ask the person to change it in My account.']) ?>
    <?= $this->insert('partials/form/input', ['name' => 'password_confirmation', 'label' => 'Confirm initial password', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'maxlength' => 72]) ?>
<?php endif; ?>
    <div class="form__actions"><button class="btn" type="submit"><?= $edit ? 'Save changes' : 'Create administrator' ?></button><a class="btn btn--secondary" href="<?= e(url('/admin/admins')) ?>">Cancel</a></div>
  </form>
</section>

<?php if ($edit && !$isSelf): ?>
<section class="card" aria-labelledby="h-pw">
  <h2 id="h-pw">Set a new password</h2>
  <p class="muted">This signs the person out everywhere. Share the new password securely and ask them to change it in My account.</p>
  <form method="post" action="<?= e(url('/admin/admins/' . $id . '/password')) ?>" class="form" data-once>
    <?= csrf_field() ?>
    <?= $this->insert('partials/form/input', ['name' => 'password', 'label' => 'New password', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'minlength' => 12, 'maxlength' => 72, 'id' => 'field-password']) ?>
    <?= $this->insert('partials/form/input', ['name' => 'password_confirmation', 'label' => 'Confirm new password', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'maxlength' => 72]) ?>
    <div class="form__actions"><button class="btn btn--secondary" type="submit">Set password</button></div>
  </form>
</section>
<?php endif; ?>
