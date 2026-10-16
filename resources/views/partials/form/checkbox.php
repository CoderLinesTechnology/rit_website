<?php
/** name, label, value ('1'), checked, required, hint, id, attrs. Label may contain no HTML (escaped); use $labelHtml for trusted markup. */
$id = $id ?? 'field-' . $name;
$err = field_error($name);
$cbValue = (string) ($value ?? '1');
$isChecked = !empty($checked) || old($name, '') === '1' || ($cbValue !== '' && old($name, '') === $cbValue);
$describe = trim((!empty($hint) ? $id . '-hint ' : '') . ($err ? $id . '-error' : ''));
?>
<div class="field checkbox-field<?= $err ? ' field--invalid' : '' ?>">
  <label class="checkbox" for="<?= e($id) ?>">
    <input type="checkbox" id="<?= e($id) ?>" name="<?= e($name) ?>" value="<?= e($cbValue) ?>"<?= checked($isChecked) ?><?= !empty($required) ? ' required aria-required="true"' : '' ?><?= $err ? ' aria-invalid="true"' : '' ?><?= $describe !== '' ? ' aria-describedby="' . e($describe) . '"' : '' ?><?php foreach (($attrs ?? []) as $an => $av): ?> <?= e((string) $an) ?>="<?= e((string) $av) ?>"<?php endforeach; ?>>
    <span><?= isset($labelHtml) ? $labelHtml : e($label) ?><?php if (!empty($required)): ?> <span class="visually-hidden">(required)</span><?php endif; ?></span>
  </label>
<?php if (!empty($hint)): ?>
  <p class="field__hint" id="<?= e($id) ?>-hint"><?= e($hint) ?></p>
<?php endif; ?>
<?php if ($err): ?>
  <p class="field__error" id="<?= e($id) ?>-error"><?= e($err) ?></p>
<?php endif; ?>
</div>
