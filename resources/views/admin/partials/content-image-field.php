<?php
/**
 * Image upload slot: current image, file input with live preview, alt text and a "remove" checkbox.
 * vars: label, current (stored path or ''), alt (current alt text), maxMb,
 *       ?name (file input, default image), ?altName (null = no alt text field), ?removeName, ?hint, ?altHint
 */
$name = $name ?? 'image';
$altName = array_key_exists('altName', get_defined_vars()) ? $altName : 'image_alt';
$removeName = $removeName ?? 'remove_image';
$current = (string) ($current ?? '');
$alt = (string) ($alt ?? '');
$curUrl = upload_url($current);
$err = field_error($name);
$altErr = $altName !== null ? field_error($altName) : null;
$id = 'field-' . $name;
$altId = 'field-' . (string) $altName;
?>
<fieldset class="field content-image<?= $err || $altErr ? ' field--invalid' : '' ?>">
  <legend class="field__label"><?= e($label) ?> <span class="field__opt">(optional)</span></legend>
  <p class="field__hint" id="<?= e($id) ?>-hint"><?= e($hint ?? 'JPEG, PNG or WebP, up to ' . (int) $maxMb . ' MB. The image is resized and stripped of hidden data (such as location) when saved.') ?></p>
<?php if ($curUrl !== ''): ?>
  <figure class="content-image__current">
    <img class="img-preview" src="<?= e($curUrl) ?>" alt="<?= e($alt !== '' ? $alt : 'Current image') ?>">
    <figcaption class="muted">Current image. Choose a new file to replace it.</figcaption>
  </figure>
<?php endif; ?>
  <label class="visually-hidden" for="<?= e($id) ?>"><?= e($label) ?> file</label>
  <input type="file" id="<?= e($id) ?>" name="<?= e($name) ?>" accept="image/jpeg,image/png,image/webp" data-preview="#preview-<?= e($name) ?>" aria-describedby="<?= e($id) ?>-hint<?= $err ? ' ' . e($id) . '-error' : '' ?>"<?= $err ? ' aria-invalid="true"' : '' ?>>
  <img class="img-preview" id="preview-<?= e($name) ?>" alt="Preview of the selected image" hidden>
<?php if ($err): ?><p class="field__error" id="<?= e($id) ?>-error"><?= e($err) ?></p><?php endif; ?>
<?php if ($altName !== null): ?>
  <div class="field content-image__alt<?= $altErr ? ' field--invalid' : '' ?>">
    <label class="field__label" for="<?= e($altId) ?>">Image description (alt text)</label>
    <p class="field__hint" id="<?= e($altId) ?>-hint"><?= e($altHint ?? 'Required whenever an image is set. Describe the photo for people who cannot see it.') ?></p>
    <input class="input" type="text" id="<?= e($altId) ?>" name="<?= e($altName) ?>" value="<?= e(old($altName, $alt ?? '')) ?>" maxlength="255" aria-describedby="<?= e($altId) ?>-hint<?= $altErr ? ' ' . e($altId) . '-error' : '' ?>"<?= $altErr ? ' aria-invalid="true"' : '' ?>>
<?php if ($altErr): ?><p class="field__error" id="<?= e($altId) ?>-error"><?= e($altErr) ?></p><?php endif; ?>
  </div>
<?php endif; ?>
<?php if ($curUrl !== ''): ?>
  <?= $this->insert('partials/form/checkbox', ['name' => $removeName, 'label' => 'Remove the current image', 'value' => '1', 'checked' => false]) ?>
<?php endif; ?>
</fieldset>
