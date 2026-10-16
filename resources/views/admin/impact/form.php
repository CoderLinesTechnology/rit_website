<?php
/** vars: $metric (?array), $v, $action, $icons, $maxMb */
$this->layout('layouts/admin');
$isEdit = $metric !== null;
$iconOptions = array_combine($icons, array_map(static fn (string $i): string => ucfirst(str_replace('-', ' ', $i)), $icons));
?>
<?= $this->insert('admin/partials/page-header', [
    'title' => $isEdit ? 'Edit impact metric' : 'New impact metric',
    'subtitle' => $isEdit ? (string) $metric['label'] : 'Fields marked * are required.',
    'actions' => [['label' => 'All metrics', 'href' => '/admin/impact', 'icon' => 'list', 'variant' => 'secondary']],
]) ?>
<?= $this->insert('partials/error-summary') ?>
<form class="form" method="post" action="<?= e(url($action)) ?>" enctype="multipart/form-data" data-once>
  <?= csrf_field() ?>
  <section class="panel">
    <div class="form-grid form-grid--2">
      <?= $this->insert('partials/form/input', ['name' => 'label', 'label' => 'Label', 'value' => $v['label'] ?? '', 'required' => true, 'maxlength' => 150, 'hint' => 'For example: Young people reached']) ?>
      <?= $this->insert('partials/form/input', ['name' => 'value', 'label' => 'Figure', 'value' => $v['value'] ?? '', 'required' => true, 'maxlength' => 40, 'hint' => 'For example: 576 or 1,200']) ?>
      <?= $this->insert('partials/form/input', ['name' => 'reporting_period', 'label' => 'Reporting period', 'value' => $v['reporting_period'] ?? '', 'maxlength' => 100, 'hint' => 'For example: 2020 to 2025']) ?>
      <?= $this->insert('partials/form/select', ['name' => 'icon', 'label' => 'Icon', 'value' => $v['icon'] ?? '', 'options' => $iconOptions, 'placeholder' => 'Default icon']) ?>
      <?= $this->insert('partials/form/input', ['name' => 'sort_order', 'label' => 'Display order', 'type' => 'number', 'value' => $v['sort_order'] ?? '0', 'hint' => 'Whole number from 0 to 9999. Smaller numbers come first.', 'attrs' => ['min' => '0', 'max' => '9999', 'step' => '1']]) ?>
      <?= $this->insert('partials/form/select', ['name' => 'status', 'label' => 'Status', 'value' => $v['status'] ?? 'draft', 'required' => true, 'placeholder' => 'Choose a status', 'options' => ['draft' => 'Draft (hidden)', 'published' => 'Published (visible)']]) ?>
      <div class="field--wide"><?= $this->insert('partials/form/textarea', ['name' => 'description', 'label' => 'Description', 'value' => $v['description'] ?? '', 'rows' => 4, 'maxlength' => 2000, 'counter' => true]) ?></div>
    </div>
    <?= $this->insert('partials/form/checkbox', ['name' => 'is_approximate', 'label' => 'This figure is approximate (shown with "approximately")', 'value' => '1', 'checked' => ($v['is_approximate'] ?? '0') === '1']) ?>
    <?= $this->insert('partials/form/checkbox', ['name' => 'show_on_homepage', 'label' => 'Show on the homepage', 'value' => '1', 'checked' => ($v['show_on_homepage'] ?? '0') === '1']) ?>
    <?= $this->insert('admin/partials/content-image-field', ['label' => 'Image', 'current' => $isEdit ? (string) $metric['image_path'] : '', 'alt' => $v['image_alt'] ?? '', 'maxMb' => $maxMb]) ?>
  </section>
  <section class="panel" aria-labelledby="sec-verify">
    <h2 class="panel__title" id="sec-verify">Verification</h2>
    <?= $this->insert('partials/form/checkbox', ['name' => 'verified', 'label' => 'This figure has been verified by the Foundation', 'value' => '1', 'checked' => ($v['verified'] ?? '0') === '1', 'hint' => 'Required to save with the status Published. It is a check only and is not stored: confirm it each time you publish a changed figure.']) ?>
  </section>
  <div class="form__actions">
    <button type="submit" class="btn" data-busy-text="Saving..."><?= $isEdit ? 'Save changes' : 'Save metric' ?></button>
    <a class="btn btn--secondary" href="<?= e(url('/admin/impact')) ?>">Cancel</a>
  </div>
</form>
