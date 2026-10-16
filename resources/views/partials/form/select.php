<?php
$id = $id ?? 'field-' . $name;
$err = field_error($name);
$value = (string) ($value ?? old($name, ''));
$required = !empty($required);
$describe = trim((!empty($hint) ? $id . '-hint ' : '') . ($err ? $id . '-error' : ''));
?>
<div class="field<?= $err ? ' field--invalid' : '' ?>">
  <label class="field__label" for="<?= e($id) ?>"><?= e($label) ?><?php if ($required): ?> <span class="field__req" aria-hidden="true">*</span><span class="visually-hidden">(required)</span><?php else: ?> <span class="field__opt">(optional)</span><?php endif; ?></label>
<?php if (!empty($hint)): ?>
  <p class="field__hint" id="<?= e($id) ?>-hint"><?= e($hint) ?></p>
<?php endif; ?>
  <select class="select" id="<?= e($id) ?>" name="<?= e($name) ?>"<?= $required ? ' required aria-required="true"' : '' ?><?= $err ? ' aria-invalid="true"' : '' ?><?= $describe !== '' ? ' aria-describedby="' . e($describe) . '"' : '' ?><?php foreach (($attrs ?? []) as $an => $av): ?> <?= e((string) $an) ?>="<?= e((string) $av) ?>"<?php endforeach; ?>>
    <option value=""<?= selected($value === '') ?>><?= e($placeholder ?? 'Select an option') ?></option>
<?php foreach (($options ?? []) as $ov => $ol): ?>
    <option value="<?= e((string) $ov) ?>"<?= selected((string) $ov === $value) ?>><?= e((string) $ol) ?></option>
<?php endforeach; ?>
  </select>
<?php if ($err): ?>
  <p class="field__error" id="<?= e($id) ?>-error"><?= e($err) ?></p>
<?php endif; ?>
</div>
