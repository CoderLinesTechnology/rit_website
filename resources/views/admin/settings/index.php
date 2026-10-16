<?php
/** vars: $groups (key => label), $group, $groupLabel, $sections (section => list of field rows), $maxMb */
$this->layout('layouts/admin');
$inputTypes = ['email' => 'email', 'url' => 'url', 'tel' => 'tel', 'text' => 'text'];
?>
<?= $this->insert('admin/partials/page-header', [
    'title' => 'Website content',
    'subtitle' => 'Text, photos and contact details shown on the public website. Changes appear straight away.',
]) ?>

<nav class="tabs" aria-label="Settings sections">
  <ul>
<?php foreach ($groups as $gk => $gl): ?>
    <li><a href="<?= e(url('/admin/settings', ['group' => $gk])) ?>"<?= $gk === $group ? ' aria-current="page"' : '' ?>><?= e($gl) ?></a></li>
<?php endforeach; ?>
  </ul>
</nav>

<?= $this->insert('partials/error-summary') ?>

<form class="form" method="post" action="<?= e(url('/admin/settings/' . $group)) ?>" enctype="multipart/form-data" data-once>
  <?= csrf_field() ?>
<?php foreach ($sections as $section => $rows): ?>
  <fieldset class="panel settings-section">
<?php if ($section !== ''): ?>    <legend><?= e($section) ?></legend>
<?php else: ?>    <legend class="visually-hidden"><?= e($groupLabel) ?></legend>
<?php endif; ?>
<?php foreach ($rows as $row):
    $def = $row['def']; $key = $row['key']; $type = (string) ($def['type'] ?? 'text');
    $name = 's[' . $key . ']';
    $hint = (string) ($def['help'] ?? '');
    ?>
    <div class="settings-field">
<?php if ($row['needsConfirmation']): ?><p class="settings-field__flag"><span class="badge badge--pending">Needs confirmation</span> <span class="muted">This fact is empty. Add it only once it has been checked.</span></p><?php endif; ?>
<?php if ($type === 'bool'): ?>
      <?= $this->insert('partials/form/checkbox', ['name' => $name, 'label' => (string) $def['label'], 'value' => '1', 'checked' => $row['value'] === '1', 'hint' => $hint]) ?>
<?php elseif ($type === 'image'): ?>
      <?= $this->insert('admin/partials/content-image-field', ['label' => (string) $def['label'], 'name' => $row['fileName'], 'altName' => null, 'removeName' => $row['removeName'], 'current' => $row['current'], 'maxMb' => $maxMb, 'hint' => ($hint !== '' ? $hint . ' ' : '') . 'JPEG, PNG or WebP, up to ' . (int) $maxMb . ' MB. Describe the image in the description field that follows.']) ?>
<?php elseif ($type === 'markup'): ?>
      <?= $this->insert('admin/partials/content-markup-field', ['name' => $name, 'label' => (string) $def['label'], 'value' => $row['value'], 'required' => !empty($def['required']), 'rows' => 8, 'maxlength' => (int) ($def['max'] ?? 0) ?: null, 'hint' => $hint !== '' ? $hint : 'Use the buttons above or type the formatting yourself.']) ?>
<?php elseif ($type === 'textarea' || $type === 'lines'): ?>
      <?= $this->insert('partials/form/textarea', ['name' => $name, 'label' => (string) $def['label'], 'value' => $row['value'], 'required' => !empty($def['required']), 'rows' => $type === 'lines' ? 6 : 4, 'maxlength' => (int) ($def['max'] ?? 0) ?: null, 'counter' => !empty($def['max']), 'hint' => trim(($type === 'lines' ? 'One per line. ' : '') . $hint)]) ?>
<?php else: ?>
      <?= $this->insert('partials/form/input', ['name' => $name, 'label' => (string) $def['label'], 'type' => $inputTypes[$type] ?? 'text', 'value' => $row['value'], 'required' => !empty($def['required']), 'maxlength' => (int) ($def['max'] ?? 0) ?: null, 'hint' => $type === 'url' ? trim('A full address starting with https://. ' . $hint) : $hint]) ?>
<?php endif; ?>
    </div>
<?php endforeach; ?>
  </fieldset>
<?php endforeach; ?>
  <div class="form__actions">
    <button type="submit" class="btn" data-busy-text="Saving...">Save <?= e(strtolower($groupLabel)) ?></button>
  </div>
</form>
