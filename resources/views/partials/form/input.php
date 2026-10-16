<?php
/** name, label, type, value, required, hint, autocomplete, maxlength, minlength, id, attrs */
$id = $id ?? 'field-' . $name;
$err = field_error($name);
$type = $type ?? 'text';
$value = $type === 'password' ? '' : ($value ?? old($name, ''));
$required = !empty($required);
$describe = trim((!empty($hint) ? $id . '-hint ' : '') . ($err ? $id . '-error' : ''));
?>
<div class="field<?= $err ? ' field--invalid' : '' ?>">
  <label class="field__label" for="<?= e($id) ?>"><?= e($label) ?><?php if ($required): ?> <span class="field__req" aria-hidden="true">*</span><span class="visually-hidden">(required)</span><?php else: ?> <span class="field__opt">(optional)</span><?php endif; ?></label>
<?php if (!empty($hint)): ?>
  <p class="field__hint" id="<?= e($id) ?>-hint"><?= e($hint) ?></p>
<?php endif; ?>
  <input class="input" type="<?= e($type) ?>" id="<?= e($id) ?>" name="<?= e($name) ?>" value="<?= e((string) $value) ?>"<?= $required ? ' required aria-required="true"' : '' ?><?= !empty($autocomplete) ? ' autocomplete="' . e($autocomplete) . '"' : '' ?><?= !empty($maxlength) ? ' maxlength="' . (int) $maxlength . '"' : '' ?><?= !empty($minlength) ? ' minlength="' . (int) $minlength . '"' : '' ?><?= $err ? ' aria-invalid="true"' : '' ?><?= $describe !== '' ? ' aria-describedby="' . e($describe) . '"' : '' ?><?php foreach (($attrs ?? []) as $an => $av): ?> <?= e((string) $an) ?>="<?= e((string) $av) ?>"<?php endforeach; ?>>
<?php if ($err): ?>
  <p class="field__error" id="<?= e($id) ?>-error"><?= e($err) ?></p>
<?php endif; ?>
</div>
