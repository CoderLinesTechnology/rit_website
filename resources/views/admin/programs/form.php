<?php
/** vars: $program (?array), $v (string map), $action, $categoryOptions, $icons, $maxMb, $images, $maxBatch */
$this->layout('layouts/admin');
$isEdit = $program !== null;
$iconOptions = array_combine($icons, array_map(static fn (string $i): string => ucfirst(str_replace(['-', '2'], [' ', ''], $i)), $icons));
?>
<?= $this->insert('admin/partials/page-header', [
    'title' => $isEdit ? 'Edit program' : 'New program',
    'subtitle' => $isEdit ? (string) $program['title'] : 'Fields marked * are required.',
    'actions' => array_values(array_filter([
        ['label' => 'All programs', 'href' => '/admin/programs', 'icon' => 'list', 'variant' => 'secondary'],
        $isEdit && $program['status'] === 'published' ? ['label' => 'View on website', 'href' => '/programs/' . $program['slug'], 'icon' => 'eye', 'variant' => 'secondary'] : null,
    ])),
]) ?>
<?= $this->insert('partials/error-summary') ?>

<form class="form" method="post" action="<?= e(url($action)) ?>" enctype="multipart/form-data" data-once>
  <?= csrf_field() ?>

  <section class="panel" aria-labelledby="sec-basics">
    <h2 class="panel__title" id="sec-basics">Basics</h2>
    <div class="form-grid form-grid--2">
      <div class="field--wide"><?= $this->insert('partials/form/input', ['name' => 'title', 'label' => 'Title', 'value' => $v['title'] ?? '', 'required' => true, 'maxlength' => 160]) ?></div>
      <?= $this->insert('partials/form/input', ['name' => 'slug', 'label' => 'Web address (slug)', 'value' => $v['slug'] ?? '', 'maxlength' => 180, 'hint' => 'Lowercase letters, numbers and hyphens. Leave empty to create it from the title.', 'attrs' => ['data-slug-source' => '#field-title']]) ?>
      <?= $this->insert('partials/form/select', ['name' => 'category_id', 'label' => 'Category', 'value' => $v['category_id'] ?? '', 'options' => $categoryOptions, 'placeholder' => 'No category']) ?>
      <?= $this->insert('partials/form/select', ['name' => 'icon', 'label' => 'Icon', 'value' => $v['icon'] ?? '', 'options' => $iconOptions, 'placeholder' => 'Default icon', 'hint' => 'Shown on program cards when there is no photo.']) ?>
      <?= $this->insert('partials/form/input', ['name' => 'sort_order', 'label' => 'Display order', 'type' => 'number', 'value' => $v['sort_order'] ?? '0', 'hint' => 'Whole number from 0 to 9999. Smaller numbers come first.', 'attrs' => ['min' => '0', 'max' => '9999', 'step' => '1']]) ?>
      <div class="field--wide"><?= $this->insert('partials/form/textarea', ['name' => 'summary', 'label' => 'Short summary', 'value' => $v['summary'] ?? '', 'required' => true, 'rows' => 3, 'maxlength' => 500, 'counter' => true, 'hint' => 'One or two sentences shown on program cards and in search results.']) ?></div>
    </div>
    <?= $this->insert('admin/partials/content-markup-field', ['name' => 'description', 'label' => 'Full description', 'value' => $v['description'] ?? '', 'required' => true, 'rows' => 12, 'maxlength' => 50000]) ?>
  </section>

  <section class="panel" aria-labelledby="sec-image">
    <h2 class="panel__title" id="sec-image">Featured image</h2>
    <?= $this->insert('admin/partials/content-image-field', ['label' => 'Featured image', 'current' => $isEdit ? (string) $program['featured_image_path'] : '', 'alt' => $v['image_alt'] ?? '', 'maxMb' => $maxMb]) ?>
  </section>

  <section class="panel" aria-labelledby="sec-details">
    <h2 class="panel__title" id="sec-details">Program details</h2>
    <div class="form-grid form-grid--2">
      <?= $this->insert('partials/form/input', ['name' => 'target_audience', 'label' => 'Who it is for', 'value' => $v['target_audience'] ?? '', 'maxlength' => 500]) ?>
      <?= $this->insert('partials/form/input', ['name' => 'duration', 'label' => 'Duration', 'value' => $v['duration'] ?? '', 'maxlength' => 120, 'hint' => 'For example: 12 weeks']) ?>
      <?= $this->insert('partials/form/input', ['name' => 'location', 'label' => 'Location', 'value' => $v['location'] ?? '', 'maxlength' => 160]) ?>
      <div class="field--wide"><?= $this->insert('partials/form/textarea', ['name' => 'eligibility', 'label' => 'Eligibility', 'value' => $v['eligibility'] ?? '', 'rows' => 4, 'maxlength' => 5000, 'hint' => 'Who can apply. Do not promise a place: applying does not guarantee acceptance.']) ?></div>
    </div>
  </section>

  <section class="panel" aria-labelledby="sec-apply">
    <h2 class="panel__title" id="sec-apply">Applications</h2>
    <?= $this->insert('partials/form/checkbox', ['name' => 'accepts_applications', 'label' => 'This program accepts applications online', 'value' => '1', 'checked' => ($v['accepts_applications'] ?? '0') === '1', 'hint' => 'Applications are only open when the program is published, this is ticked, today is inside the dates below, and applications are switched on in Website content settings.']) ?>
    <div class="form-grid form-grid--2">
      <?= $this->insert('partials/form/input', ['name' => 'application_opens_on', 'label' => 'Applications open on', 'type' => 'date', 'value' => $v['application_opens_on'] ?? '', 'hint' => 'Leave empty to open straight away.']) ?>
      <?= $this->insert('partials/form/input', ['name' => 'application_closes_on', 'label' => 'Applications close on', 'type' => 'date', 'value' => $v['application_closes_on'] ?? '', 'hint' => 'The last day applications are accepted. Leave empty for no end date.']) ?>
      <div class="field--wide"><?= $this->insert('partials/form/textarea', ['name' => 'application_instructions', 'label' => 'Application instructions', 'value' => $v['application_instructions'] ?? '', 'rows' => 4, 'maxlength' => 5000]) ?></div>
    </div>
  </section>

  <section class="panel" aria-labelledby="sec-publish">
    <h2 class="panel__title" id="sec-publish">Publishing and search</h2>
    <div class="form-grid form-grid--2">
      <?= $this->insert('partials/form/select', ['name' => 'status', 'label' => 'Status', 'value' => $v['status'] ?? 'draft', 'required' => true, 'placeholder' => 'Choose a status', 'options' => ['draft' => 'Draft (hidden)', 'published' => 'Published (visible)', 'archived' => 'Archived (hidden)']]) ?>
      <div><?= $this->insert('partials/form/checkbox', ['name' => 'is_featured', 'label' => 'Feature this program on the homepage', 'value' => '1', 'checked' => ($v['is_featured'] ?? '0') === '1']) ?></div>
      <div class="field--wide"><?= $this->insert('partials/form/input', ['name' => 'seo_title', 'label' => 'Search engine title', 'value' => $v['seo_title'] ?? '', 'maxlength' => 150, 'hint' => 'About 60 characters is ideal. Leave empty to use the program title.']) ?></div>
      <div class="field--wide"><?= $this->insert('partials/form/textarea', ['name' => 'seo_description', 'label' => 'Search engine description', 'value' => $v['seo_description'] ?? '', 'rows' => 3, 'maxlength' => 300, 'counter' => true, 'hint' => 'About 155 characters is ideal. Leave empty to use the summary.']) ?></div>
    </div>
  </section>

  <div class="form__actions">
    <button type="submit" class="btn" data-busy-text="Saving..."><?= $isEdit ? 'Save changes' : 'Create program' ?></button>
    <a class="btn btn--secondary" href="<?= e(url('/admin/programs')) ?>">Cancel</a>
  </div>
</form>

<?php if ($isEdit): $pid = (int) $program['id']; ?>
<section class="panel content-narrow" id="program-images" aria-labelledby="sec-gallery">
  <h2 class="panel__title" id="sec-gallery">Additional images</h2>
  <p class="muted">Photos shown in the program's gallery on its page. Up to <?= (int) $maxBatch ?> at a time.</p>
<?php if ($images): ?>
  <ul class="thumb-list">
<?php foreach ($images as $img): ?>
    <li>
      <img class="table-thumb" src="<?= e(upload_url($img['thumb_path'])) ?>" alt="<?= e($img['alt_text']) ?>" width="56" height="56" loading="lazy">
      <span class="thumb-list__alt"><?= e($img['alt_text']) ?></span>
<?= $this->insert('admin/partials/content-delete-form', ['action' => '/admin/programs/' . $pid . '/images/' . (int) $img['id'] . '/delete', 'confirm' => 'Delete this image? This cannot be undone.', 'label' => 'Delete image: ' . $img['alt_text']]) ?>
    </li>
<?php endforeach; ?>
  </ul>
<?php else: ?>
  <p class="muted">No additional images yet.</p>
<?php endif; ?>
  <form class="form" method="post" action="<?= e(url('/admin/programs/' . $pid . '/images')) ?>" enctype="multipart/form-data" data-once>
    <?= csrf_field() ?>
    <div class="field<?= field_error('images') ? ' field--invalid' : '' ?>">
      <label class="field__label" for="field-images">Add images <span class="field__req" aria-hidden="true">*</span><span class="visually-hidden">(required)</span></label>
      <p class="field__hint" id="field-images-hint">JPEG, PNG or WebP, up to <?= (int) $maxMb ?> MB each. You can choose several files.</p>
      <input type="file" id="field-images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required aria-describedby="field-images-hint"<?= field_error('images') ? ' aria-invalid="true"' : '' ?>>
<?php if (field_error('images')): ?><p class="field__error"><?= e((string) field_error('images')) ?></p><?php endif; ?>
    </div>
    <?= $this->insert('partials/form/input', ['name' => 'image_batch_alt', 'label' => 'Description for these images', 'value' => old('image_batch_alt'), 'required' => true, 'maxlength' => 255, 'hint' => 'Describe the photos for people who cannot see them. The same description is used for every image in this batch; you can upload them in smaller batches to describe them differently.']) ?>
    <div class="form__actions"><button type="submit" class="btn btn--secondary" data-busy-text="Uploading...">Upload images</button></div>
  </form>
</section>

<section class="danger-zone" aria-labelledby="sec-danger">
  <h2 id="sec-danger">Delete this program</h2>
  <p>Applications already received for this program are kept; they simply will no longer be linked to a program. The program's images are deleted. This cannot be undone. To hide a program without deleting it, set its status to Archived.</p>
<?= $this->insert('admin/partials/content-delete-form', ['action' => '/admin/programs/' . $pid . '/delete', 'text' => 'Delete program', 'confirm' => 'Delete the program "' . $program['title'] . '"? Applications already received for it are kept (they will just no longer be linked to a program). Its images are deleted. This cannot be undone.', 'label' => 'Delete program']) ?>
</section>
<?php endif; ?>
