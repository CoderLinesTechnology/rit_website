<?php
/** vars: $category (?array), $v, $action, $slugLocked */
$this->layout('layouts/admin');
$isEdit = $category !== null;
?>
<?= $this->insert('admin/partials/page-header', [
    'title' => $isEdit ? 'Edit category' : 'New category',
    'subtitle' => $isEdit ? (string) $category['name'] : 'Fields marked * are required.',
    'actions' => [['label' => 'All categories', 'href' => '/admin/news-categories', 'icon' => 'list', 'variant' => 'secondary']],
]) ?>
<?= $this->insert('partials/error-summary') ?>
<form class="form" method="post" action="<?= e(url($action)) ?>" data-once>
  <?= csrf_field() ?>
  <section class="panel">
    <div class="form-grid form-grid--2">
      <?= $this->insert('partials/form/input', ['name' => 'name', 'label' => 'Name', 'value' => $v['name'] ?? '', 'required' => true, 'maxlength' => 120]) ?>
<?php if ($slugLocked): ?>
      <div class="field">
        <span class="field__label">Web address (slug)</span>
        <p class="field__hint">This category is used by the website (success stories) so its web address cannot be changed.</p>
        <code><?= e($category['slug']) ?></code>
      </div>
<?php else: ?>
      <?= $this->insert('partials/form/input', ['name' => 'slug', 'label' => 'Web address (slug)', 'value' => $v['slug'] ?? '', 'maxlength' => 140, 'hint' => 'Lowercase letters, numbers and hyphens. Leave empty to create it from the name.', 'attrs' => ['data-slug-source' => '#field-name']]) ?>
<?php endif; ?>
      <?= $this->insert('partials/form/input', ['name' => 'sort_order', 'label' => 'Display order', 'type' => 'number', 'value' => $v['sort_order'] ?? '0', 'hint' => 'Whole number from 0 to 9999. Smaller numbers come first.', 'attrs' => ['min' => '0', 'max' => '9999', 'step' => '1']]) ?>
      <div class="field--wide"><?= $this->insert('partials/form/textarea', ['name' => 'description', 'label' => 'Description', 'value' => $v['description'] ?? '', 'rows' => 4, 'maxlength' => 2000, 'counter' => true]) ?></div>
    </div>
  </section>
  <div class="form__actions">
    <button type="submit" class="btn" data-busy-text="Saving..."><?= $isEdit ? 'Save changes' : 'Create category' ?></button>
    <a class="btn btn--secondary" href="<?= e(url('/admin/news-categories')) ?>">Cancel</a>
  </div>
</form>
