<?php
/** vars: $album, $images, $v, $maxBatch, $maxMb */
$this->layout('layouts/admin');
$aid = (int) $album['id'];
?>
<?= $this->insert('admin/partials/page-header', [
    'title' => (string) $album['name'],
    'subtitle' => count($images) . (count($images) === 1 ? ' photo' : ' photos') . ' in this album',
    'actions' => array_values(array_filter([
        ['label' => 'All albums', 'href' => '/admin/gallery', 'icon' => 'list', 'variant' => 'secondary'],
        ['label' => 'Edit album', 'href' => '/admin/gallery/albums/' . $aid . '/edit', 'icon' => 'pencil', 'variant' => 'secondary'],
        (int) $album['is_published'] === 1 ? ['label' => 'View on website', 'href' => '/gallery/' . $album['slug'], 'icon' => 'eye', 'variant' => 'secondary'] : null,
    ])),
]) ?>
<?php if ((int) $album['is_published'] !== 1): ?>
<div class="alert alert--info" role="status"><?= icon('info-circle') ?><p>This album is hidden from the website. Publish the album (Edit album) for its published photos to appear.</p></div>
<?php endif; ?>
<?= $this->insert('partials/error-summary') ?>

<section class="panel" aria-labelledby="sec-upload">
  <h2 class="panel__title" id="sec-upload">Upload photos</h2>
  <form class="form" method="post" action="<?= e(url('/admin/gallery/albums/' . $aid . '/images')) ?>" enctype="multipart/form-data" data-once>
    <?= csrf_field() ?>
    <div class="field<?= field_error('images') ? ' field--invalid' : '' ?>">
      <label class="field__label" for="field-images">Photos <span class="field__req" aria-hidden="true">*</span><span class="visually-hidden">(required)</span></label>
      <p class="field__hint" id="field-images-hint">Choose up to <?= (int) $maxBatch ?> JPEG, PNG or WebP photos at a time, each up to <?= (int) $maxMb ?> MB. They are resized and stripped of hidden data (such as location) when saved.</p>
      <input type="file" id="field-images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required aria-describedby="field-images-hint<?= field_error('images') ? ' field-images-error' : '' ?>"<?= field_error('images') ? ' aria-invalid="true"' : '' ?>>
<?php if (field_error('images')): ?><p class="field__error" id="field-images-error"><?= e((string) field_error('images')) ?></p><?php endif; ?>
    </div>
    <div class="form-grid form-grid--2">
      <?= $this->insert('partials/form/input', ['name' => 'alt_text', 'label' => 'Photo description (alt text)', 'value' => $v['alt_text'] ?? '', 'required' => true, 'maxlength' => 255, 'hint' => 'Describe the photos for people who cannot see them. Used for every photo in this upload; you can change it per photo afterwards.']) ?>
      <?= $this->insert('partials/form/input', ['name' => 'caption', 'label' => 'Caption', 'value' => $v['caption'] ?? '', 'maxlength' => 500]) ?>
      <?= $this->insert('partials/form/input', ['name' => 'taken_on', 'label' => 'Date taken', 'type' => 'date', 'value' => $v['taken_on'] ?? '']) ?>
    </div>
    <?= $this->insert('partials/form/checkbox', ['name' => 'consent_confirmed', 'label' => 'The Foundation has permission to publish these photos, including of any identifiable people (parental consent for minors)', 'value' => '1', 'checked' => ($v['consent_confirmed'] ?? '0') === '1']) ?>
    <?= $this->insert('partials/form/checkbox', ['name' => 'is_published', 'label' => 'Publish these photos now', 'value' => '1', 'checked' => ($v['is_published'] ?? '0') === '1', 'hint' => 'Publishing requires the permission box above. Unpublished photos stay private and can be published later.']) ?>
    <div class="form__actions"><button type="submit" class="btn" data-busy-text="Uploading...">Upload photos</button></div>
  </form>
</section>

<h2>Photos</h2>
<?php if (!$images): ?>
<?= $this->insert('admin/partials/empty-state', ['icon' => 'image', 'title' => 'No photos in this album yet', 'text' => 'Use the form above to upload the first photos.']) ?>
<?php else: ?>
<ul class="photo-grid">
<?php foreach ($images as $img): $iid = (int) $img['id']; $label = (string) ($img['title'] ?: $img['alt_text']); ?>
  <li class="photo-card">
    <img src="<?= e(upload_url($img['thumb_path'])) ?>" alt="<?= e($img['alt_text']) ?>" width="<?= (int) min(240, $img['width']) ?>" height="<?= (int) round(min(240, $img['width']) * $img['height'] / max(1, $img['width'])) ?>" loading="lazy">
    <div class="photo-card__body">
      <p class="photo-card__title"><?= e($label) ?></p>
      <p class="photo-card__badges">
        <?= $this->insert('admin/partials/badge', ['status' => (int) $img['is_published'] === 1 ? 'published' : 'draft']) ?>
<?php if ((int) $img['is_featured'] === 1): ?><span class="badge badge--featured">Featured</span><?php endif; ?>
        <?= (int) $img['consent_confirmed'] === 1 ? '<span class="badge badge--published">Permission confirmed</span>' : '<span class="badge badge--pending">No permission yet</span>' ?>
      </p>
      <p class="photo-card__actions">
        <a class="btn btn--secondary btn--sm" href="<?= e(url('/admin/gallery/images/' . $iid . '/edit')) ?>" aria-label="Edit photo: <?= e($label) ?>"><?= icon('pencil') ?> Edit</a>
<?= $this->insert('admin/partials/content-delete-form', ['action' => '/admin/gallery/images/' . $iid . '/delete', 'confirm' => 'Delete this photo? The picture file is deleted too. This cannot be undone.', 'label' => 'Delete photo: ' . $label]) ?>
      </p>
    </div>
  </li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
