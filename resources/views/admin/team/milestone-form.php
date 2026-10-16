<?php
/** vars: $milestone (?array), $v, $action */
$this->layout('layouts/admin');
$isEdit = $milestone !== null;
?>
<?= $this->insert('admin/partials/page-header', [
    'title' => $isEdit ? 'Edit milestone' : 'New milestone',
    'subtitle' => $isEdit ? (string) $milestone['title'] : 'Fields marked * are required.',
    'actions' => [['label' => 'Team & milestones', 'href' => '/admin/team#milestones', 'icon' => 'list', 'variant' => 'secondary']],
]) ?>
<?= $this->insert('partials/error-summary') ?>
<form class="form" method="post" action="<?= e(url($action)) ?>" data-once>
  <?= csrf_field() ?>
  <section class="panel">
    <div class="form-grid form-grid--2">
      <?= $this->insert('partials/form/input', ['name' => 'period_label', 'label' => 'Date or period', 'value' => $v['period_label'] ?? '', 'required' => true, 'maxlength' => 40, 'hint' => 'For example: 2021 or March 2023']) ?>
      <?= $this->insert('partials/form/input', ['name' => 'sort_order', 'label' => 'Display order', 'type' => 'number', 'value' => $v['sort_order'] ?? '0', 'hint' => 'Whole number from 0 to 9999. Smaller numbers come first.', 'attrs' => ['min' => '0', 'max' => '9999', 'step' => '1']]) ?>
      <div class="field--wide"><?= $this->insert('partials/form/input', ['name' => 'title', 'label' => 'Milestone', 'value' => $v['title'] ?? '', 'required' => true, 'maxlength' => 190]) ?></div>
      <div class="field--wide"><?= $this->insert('partials/form/textarea', ['name' => 'description', 'label' => 'Details', 'value' => $v['description'] ?? '', 'rows' => 4, 'maxlength' => 2000, 'counter' => true]) ?></div>
    </div>
    <?= $this->insert('partials/form/checkbox', ['name' => 'is_verified', 'label' => 'The date and facts of this milestone have been verified', 'value' => '1', 'checked' => ($v['is_verified'] ?? '0') === '1']) ?>
    <?= $this->insert('partials/form/checkbox', ['name' => 'is_published', 'label' => 'Show this milestone on the website', 'value' => '1', 'checked' => ($v['is_published'] ?? '0') === '1', 'hint' => 'Requires the verification box above.']) ?>
  </section>
  <div class="form__actions">
    <button type="submit" class="btn" data-busy-text="Saving..."><?= $isEdit ? 'Save changes' : 'Save milestone' ?></button>
    <a class="btn btn--secondary" href="<?= e(url('/admin/team#milestones')) ?>">Cancel</a>
  </div>
</form>
