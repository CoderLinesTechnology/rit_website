<?php
/** vars: $album (?array), $v, $action */
$this->layout('layouts/admin');
$isEdit = $album !== null;
?>
<?= $this->insert('admin/partials/page-header', [
    'title' => $isEdit ? 'Edit album' : 'New album',
    'subtitle' => $isEdit ? (string) $album['name'] : 'Fields marked * are required.',
    'actions' => array_values(array_filter([
        ['label' => 'All albums', 'href' => '/admin/gallery', 'icon' => 'list', 'variant' => 'secondary'],
        $isEdit ? ['label' => 'Photos in this album', 'href' => '/admin/gallery/albums/' . (int) $album['id'], 'icon' => 'images', 'variant' => 'secondary'] : null,
    ])),
]) ?>
<?= $this->insert('partials/error-summary') ?>
<form class="form" method="post" action="<?= e(url($action)) ?>" data-once>
  <?= csrf_field() ?>
  <section class="panel">
    <div class="form-grid form-grid--2">
      <?= $this->insert('partials/form/input', ['name' => 'name', 'label' => 'Album name', 'value' => $v['name'] ?? '', 'required' => true, 'maxlength' => 150]) ?>
      <?= $this->insert('partials/form/input', ['name' => 'slug', 'label' => 'Web address (slug)', 'value' => $v['slug'] ?? '', 'maxlength' => 170, 'hint' => 'Lowercase letters, numbers and hyphens. Leave empty to create it from the name.', 'attrs' => ['data-slug-source' => '#field-name']]) ?>
      <?= $this->insert('partials/form/input', ['name' => 'event_date', 'label' => 'Event date', 'type' => 'date', 'value' => $v['event_date'] ?? '']) ?>
      <?= $this->insert('partials/form/input', ['name' => 'sort_order', 'label' => 'Display order', 'type' => 'number', 'value' => $v['sort_order'] ?? '0', 'hint' => 'Whole number from 0 to 9999. Smaller numbers come first.', 'attrs' => ['min' => '0', 'max' => '9999', 'step' => '1']]) ?>
      <div class="field--wide"><?= $this->insert('partials/form/input', ['name' => 'external_url', 'label' => 'Link to more photos', 'type' => 'url', 'value' => $v['external_url'] ?? '', 'maxlength' => 500, 'hint' => 'Optional. A full address starting with https:// (for example a shared photo folder).']) ?></div>
      <div class="field--wide"><?= $this->insert('partials/form/textarea', ['name' => 'description', 'label' => 'Description', 'value' => $v['description'] ?? '', 'rows' => 4, 'maxlength' => 2000, 'counter' => true]) ?></div>
    </div>
    <?= $this->insert('partials/form/checkbox', ['name' => 'is_published', 'label' => 'Show this album on the website', 'value' => '1', 'checked' => ($v['is_published'] ?? '0') === '1', 'hint' => 'Photos inside still need to be published individually.']) ?>
  </section>
  <div class="form__actions">
    <button type="submit" class="btn" data-busy-text="Saving..."><?= $isEdit ? 'Save changes' : 'Create album' ?></button>
    <a class="btn btn--secondary" href="<?= e(url($isEdit ? '/admin/gallery/albums/' . (int) $album['id'] : '/admin/gallery')) ?>">Cancel</a>
  </div>
</form>
