<?php /** $label, $value, ?$caption, $icon, ?$variant maroon|slate|green|amber, ?$href */
$tag = !empty($href) ? 'a' : 'div';
$variant = $variant ?? 'maroon';
?>
<<?= $tag ?> class="stat stat--<?= e($variant) ?>"<?= !empty($href) ? ' href="' . e(url($href)) . '"' : '' ?>>
  <span class="stat__icon"><?= icon($icon ?? 'grid') ?></span>
  <span class="stat__label"><?= e($label) ?></span>
  <span class="stat__value"><?= e((string) $value) ?></span>
<?php if (!empty($caption)): ?><span class="stat__caption"><?= e($caption) ?></span><?php endif; ?>
</<?= $tag ?>>
