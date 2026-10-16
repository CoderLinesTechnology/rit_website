<?php
$id = $id ?? 'field-' . $name;
$err = field_error($name);
$value = (string) ($value ?? old($name, ''));
$required = !empty($required);
$describe = trim((!empty($hint) ? $id . '-hint ' : '') . ($err ? $id . '-error' : ''));
?>
<fieldset class="field<?= $err ? ' field--invalid' : '' ?>"<?= $describe !== '' ? ' aria-describedby="' . e($describe) . '"' : '' ?>>
  <legend class="field__label"><?= e($label) ?><?php if ($required): ?> <span class="field__req" aria-hidden="true">*</span><span class="visually-hidden">(required)</span><?php else: ?> <span class="field__opt">(optional)</span><?php endif; ?></legend>
<?php if (!empty($hint)): ?>
  <p class="field__hint" id="<?= e($id) ?>-hint"><?= e($hint) ?></p>
<?php endif; ?>
  <div class="radios" id="<?= e($id) ?>">
<?php $i = 0; foreach (($options ?? []) as $ov => $ol): $i++; $rid = $id . '-' . $i; ?>
    <label class="radio" for="<?= e($rid) ?>"><input type="radio" id="<?= e($rid) ?>" name="<?= e($name) ?>" value="<?= e((string) $ov) ?>"<?= checked((string) $ov === $value) ?><?= $required && $i === 1 ? ' required' : '' ?><?= $err ? ' aria-invalid="true"' : '' ?>><span><?= e((string) $ol) ?></span></label>
<?php endforeach; ?>
  </div>
<?php if ($err): ?>
  <p class="field__error" id="<?= e($id) ?>-error"><?= e($err) ?></p>
<?php endif; ?>
</fieldset>
