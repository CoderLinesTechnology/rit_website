<?php
$id = $id ?? 'field-' . $name;
$err = field_error($name);
$value = $value ?? old($name, '');
$required = !empty($required);
$describe = trim((!empty($hint) ? $id . '-hint ' : '') . ($err ? $id . '-error' : ''));
?>
<div class="field<?= $err ? ' field--invalid' : '' ?>">
  <label class="field__label" for="<?= e($id) ?>"><?= e($label) ?><?php if ($required): ?> <span class="field__req" aria-hidden="true">*</span><span class="visually-hidden">(required)</span><?php else: ?> <span class="field__opt">(optional)</span><?php endif; ?></label>
<?php if (!empty($hint)): ?>
  <p class="field__hint" id="<?= e($id) ?>-hint"><?= e($hint) ?></p>
<?php endif; ?>
  <textarea class="textarea" id="<?= e($id) ?>" name="<?= e($name) ?>" rows="<?= (int) ($rows ?? 5) ?>"<?= $required ? ' required aria-required="true"' : '' ?><?= !empty($maxlength) ? ' maxlength="' . (int) $maxlength . '"' : '' ?><?= !empty($minlength) ? ' minlength="' . (int) $minlength . '"' : '' ?><?= !empty($counter) ? ' data-counter="1"' : '' ?><?= $err ? ' aria-invalid="true"' : '' ?><?= $describe !== '' ? ' aria-describedby="' . e($describe) . '"' : '' ?><?php foreach (($attrs ?? []) as $an => $av): ?> <?= e((string) $an) ?>="<?= e((string) $av) ?>"<?php endforeach; ?>><?= e((string) $value) ?></textarea>
<?php if ($err): ?>
  <p class="field__error" id="<?= e($id) ?>-error"><?= e($err) ?></p>
<?php endif; ?>
</div>
