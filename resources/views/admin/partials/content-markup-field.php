<?php
/**
 * Long text with the formatting toolbar and a short help.
 * vars: name, label, value, ?required, ?hint, ?rows, ?maxlength
 */
$id = 'field-' . $name;
$err = field_error($name);
$required = !empty($required);
$describe = trim($id . '-hint' . ($err ? ' ' . $id . '-error' : ''));
$tools = [['bold', 'B', 'Bold'], ['italic', 'I', 'Italic'], ['h2', 'H2', 'Heading'], ['h3', 'H3', 'Subheading'], ['ul', '• List', 'Bulleted list'], ['ol', '1. List', 'Numbered list'], ['quote', '“ ”', 'Quote'], ['link', 'Link', 'Link']];
?>
<div class="field<?= $err ? ' field--invalid' : '' ?>">
  <label class="field__label" for="<?= e($id) ?>"><?= e($label) ?><?php if ($required): ?> <span class="field__req" aria-hidden="true">*</span><span class="visually-hidden">(required)</span><?php else: ?> <span class="field__opt">(optional)</span><?php endif; ?></label>
  <p class="field__hint" id="<?= e($id) ?>-hint"><?= e($hint ?? 'Use the buttons above or type the formatting yourself.') ?></p>
  <div class="markup-toolbar" data-markup-toolbar role="toolbar" aria-label="Formatting for <?= e($label) ?>">
<?php foreach ($tools as [$cmd, $text, $title]): ?>
    <button type="button" data-insert="<?= e($cmd) ?>" title="<?= e($title) ?>" aria-label="<?= e($title) ?>"><?= e($text) ?></button>
<?php endforeach; ?>
  </div>
  <textarea class="textarea" id="<?= e($id) ?>" name="<?= e($name) ?>" rows="<?= (int) ($rows ?? 10) ?>"<?= $required ? ' required aria-required="true"' : '' ?><?= !empty($maxlength) ? ' maxlength="' . (int) $maxlength . '" data-counter="1"' : '' ?><?= $err ? ' aria-invalid="true"' : '' ?> aria-describedby="<?= e($describe) ?>"><?= e((string) $value) ?></textarea>
<?php if ($err): ?><p class="field__error" id="<?= e($id) ?>-error"><?= e($err) ?></p><?php endif; ?>
  <details class="content-help">
    <summary>Formatting help</summary>
    <ul>
      <li><code>**bold text**</code> for <strong>bold</strong></li>
      <li><code>*italic text*</code> for <em>italic</em></li>
      <li><code>## Heading</code> on its own line for a heading</li>
      <li><code>- item</code> at the start of a line for a bulleted list</li>
      <li><code>[link text](https://example.org)</code> for a link</li>
    </ul>
  </details>
</div>
