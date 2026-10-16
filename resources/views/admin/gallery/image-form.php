<?php
/** vars: $image, $v, $albums (id => name), $action */
$this->layout('layouts/admin');
?>
<?= $this->insert('admin/partials/page-header', [
    'title' => 'Edit photo',
    'actions' => [['label' => 'Back to album', 'href' => '/admin/gallery/albums/' . (int) $image['category_id'], 'icon' => 'images', 'variant' => 'secondary']],
]) ?>
<?= $this->insert('partials/error-summary') ?>
<form class="form" method="post" action="<?= e(url($action)) ?>" data-once>
  <?= csrf_field() ?>
  <section class="panel">
    <figure class="content-image__current">
      <img class="img-preview" src="<?= e(upload_url($image['thumb_path'])) ?>" alt="<?= e($image['alt_text']) ?>">
    </figure>
    <div class="form-grid form-grid--2">
      <?= $this->insert('partials/form/input', ['name' => 'title', 'label' => 'Title', 'value' => $v['title'] ?? '', 'maxlength' => 190]) ?>
      <?= $this->insert('partials/form/select', ['name' => 'category_id', 'label' => 'Album', 'value' => $v['category_id'] ?? '', 'required' => true, 'options' => $albums, 'placeholder' => 'Choose an album', 'hint' => 'Choose a different album to move the photo.']) ?>
      <div class="field--wide"><?= $this->insert('partials/form/input', ['name' => 'alt_text', 'label' => 'Photo description (alt text)', 'value' => $v['alt_text'] ?? '', 'required' => true, 'maxlength' => 255, 'hint' => 'Describe the photo for people who cannot see it.']) ?></div>
      <?= $this->insert('partials/form/input', ['name' => 'caption', 'label' => 'Caption', 'value' => $v['caption'] ?? '', 'maxlength' => 500]) ?>
      <?= $this->insert('partials/form/input', ['name' => 'taken_on', 'label' => 'Date taken', 'type' => 'date', 'value' => $v['taken_on'] ?? '']) ?>
      <?= $this->insert('partials/form/input', ['name' => 'sort_order', 'label' => 'Display order', 'type' => 'number', 'value' => $v['sort_order'] ?? '0', 'hint' => 'Whole number. Smaller numbers come first.', 'attrs' => ['min' => '0', 'max' => '99999', 'step' => '1']]) ?>
    </div>
    <?= $this->insert('partials/form/checkbox', ['name' => 'consent_confirmed', 'label' => 'The Foundation has permission to publish this photo, including of any identifiable people (parental consent for minors)', 'value' => '1', 'checked' => ($v['consent_confirmed'] ?? '0') === '1']) ?>
    <?= $this->insert('partials/form/checkbox', ['name' => 'is_published', 'label' => 'Publish this photo', 'value' => '1', 'checked' => ($v['is_published'] ?? '0') === '1', 'hint' => 'Requires the permission box above.']) ?>
    <?= $this->insert('partials/form/checkbox', ['name' => 'is_featured', 'label' => 'Feature this photo on the homepage', 'value' => '1', 'checked' => ($v['is_featured'] ?? '0') === '1']) ?>
  </section>
  <div class="form__actions">
    <button type="submit" class="btn" data-busy-text="Saving...">Save photo</button>
    <a class="btn btn--secondary" href="<?= e(url('/admin/gallery/albums/' . (int) $image['category_id'])) ?>">Cancel</a>
  </div>
</form>
<section class="danger-zone" aria-labelledby="sec-danger">
  <h2 id="sec-danger">Delete this photo</h2>
  <p>The photo and its picture files are permanently deleted. To hide it without deleting, untick Publish.</p>
<?= $this->insert('admin/partials/content-delete-form', ['action' => '/admin/gallery/images/' . (int) $image['id'] . '/delete', 'text' => 'Delete photo', 'confirm' => 'Delete this photo? The picture file is deleted too. This cannot be undone.', 'label' => 'Delete photo']) ?>
</section>
