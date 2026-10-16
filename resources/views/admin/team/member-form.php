<?php
/** vars: $member (?array), $v, $action, $maxMb */
$this->layout('layouts/admin');
$isEdit = $member !== null;
?>
<?= $this->insert('admin/partials/page-header', [
    'title' => $isEdit ? 'Edit team member' : 'New team member',
    'subtitle' => $isEdit ? (string) $member['full_name'] : 'Fields marked * are required.',
    'actions' => [['label' => 'Team & milestones', 'href' => '/admin/team', 'icon' => 'list', 'variant' => 'secondary']],
]) ?>
<?= $this->insert('partials/error-summary') ?>
<form class="form" method="post" action="<?= e(url($action)) ?>" enctype="multipart/form-data" data-once>
  <?= csrf_field() ?>
  <section class="panel">
    <div class="form-grid form-grid--2">
      <?= $this->insert('partials/form/input', ['name' => 'full_name', 'label' => 'Full name', 'value' => $v['full_name'] ?? '', 'required' => true, 'maxlength' => 150]) ?>
      <?= $this->insert('partials/form/input', ['name' => 'role_title', 'label' => 'Role or title', 'value' => $v['role_title'] ?? '', 'required' => true, 'maxlength' => 150]) ?>
      <?= $this->insert('partials/form/input', ['name' => 'sort_order', 'label' => 'Display order', 'type' => 'number', 'value' => $v['sort_order'] ?? '0', 'hint' => 'Whole number from 0 to 9999. Smaller numbers come first.', 'attrs' => ['min' => '0', 'max' => '9999', 'step' => '1']]) ?>
      <div class="field--wide"><?= $this->insert('partials/form/textarea', ['name' => 'bio', 'label' => 'Short biography', 'value' => $v['bio'] ?? '', 'rows' => 5, 'maxlength' => 3000, 'counter' => true]) ?></div>
    </div>
    <?= $this->insert('admin/partials/content-image-field', ['label' => 'Photo', 'altName' => null, 'current' => $isEdit ? (string) $member['photo_path'] : '', 'maxMb' => $maxMb, 'hint' => 'JPEG, PNG or WebP, up to ' . (int) $maxMb . ' MB. The person\'s name is used to describe the photo for screen readers.']) ?>
  </section>
  <section class="panel" aria-labelledby="sec-consent">
    <h2 class="panel__title" id="sec-consent">Approval and publishing</h2>
    <?= $this->insert('partials/form/checkbox', ['name' => 'consent_confirmed', 'label' => 'This person approved the details and photo shown', 'value' => '1', 'checked' => ($v['consent_confirmed'] ?? '0') === '1']) ?>
    <?= $this->insert('partials/form/checkbox', ['name' => 'is_published', 'label' => 'Show this person on the website', 'value' => '1', 'checked' => ($v['is_published'] ?? '0') === '1', 'hint' => 'Requires the approval box above.']) ?>
  </section>
  <div class="form__actions">
    <button type="submit" class="btn" data-busy-text="Saving..."><?= $isEdit ? 'Save changes' : 'Save team member' ?></button>
    <a class="btn btn--secondary" href="<?= e(url('/admin/team')) ?>">Cancel</a>
  </div>
</form>
