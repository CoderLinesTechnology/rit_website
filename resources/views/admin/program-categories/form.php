<?php
/** vars: $category (?array), $v, $action, $icons, $maxMb */
$this->layout('layouts/admin');
$isEdit = $category !== null;
$iconOptions = array_combine($icons, array_map(static fn (string $i): string => ucfirst(str_replace(['-', '2'], [' ', ''], $i)), $icons));
?>
<?= $this->insert('admin/partials/page-header', [
    'title' => $isEdit ? 'Edit category' : 'New category',
    'subtitle' => $isEdit ? (string) $category['name'] : 'Fields marked * are required.',
    'actions' => [['label' => 'All categories', 'href' => '/admin/program-categories', 'icon' => 'list', 'variant' => 'secondary']],
]) ?>
<?= $this->insert('partials/error-summary') ?>
<form class="form" method="post" action="<?= e(url($action)) ?>" enctype="multipart/form-data" data-once>
  <?= csrf_field() ?>
  <section class="panel">
    <div class="form-grid form-grid--2">
      <?= $this->insert('partials/form/input', ['name' => 'name', 'label' => 'Name', 'value' => $v['name'] ?? '', 'required' => true, 'maxlength' => 120]) ?>
      <?= $this->insert('partials/form/input', ['name' => 'slug', 'label' => 'Web address (slug)', 'value' => $v['slug'] ?? '', 'maxlength' => 140, 'hint' => 'Lowercase letters, numbers and hyphens. Leave empty to create it from the name.', 'attrs' => ['data-slug-source' => '#field-name']]) ?>
      <?= $this->insert('partials/form/select', ['name' => 'icon', 'label' => 'Icon', 'value' => $v['icon'] ?? '', 'options' => $iconOptions, 'placeholder' => 'Default icon']) ?>
      <?= $this->insert('partials/form/input', ['name' => 'sort_order', 'label' => 'Display order', 'type' => 'number', 'value' => $v['sort_order'] ?? '0', 'hint' => 'Whole number from 0 to 9999. Smaller numbers come first.', 'attrs' => ['min' => '0', 'max' => '9999', 'step' => '1']]) ?>
      <div class="field--wide"><?= $this->insert('partials/form/textarea', ['name' => 'description', 'label' => 'Description', 'value' => $v['description'] ?? '', 'rows' => 4, 'maxlength' => 2000, 'counter' => true]) ?></div>
    </div>
    <?= $this->insert('admin/partials/content-image-field', ['label' => 'Image', 'current' => $isEdit ? (string) $category['image_path'] : '', 'alt' => $v['image_alt'] ?? '', 'maxMb' => $maxMb]) ?>
  </section>
  <div class="form__actions">
    <button type="submit" class="btn" data-busy-text="Saving..."><?= $isEdit ? 'Save changes' : 'Create category' ?></button>
    <a class="btn btn--secondary" href="<?= e(url('/admin/program-categories')) ?>">Cancel</a>
  </div>
</form>
