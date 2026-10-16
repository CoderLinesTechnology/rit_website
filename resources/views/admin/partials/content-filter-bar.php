<?php
/**
 * GET filter bar. vars: action, filters (['q'=>..., 'status'=>..., 'category'=>...]), ?statuses (value=>label), ?categories (id=>name), ?searchLabel
 */
$filters = $filters ?? [];
?>
<form class="filters" method="get" action="<?= e(url($action)) ?>" role="search">
  <div class="field">
    <label class="field__label" for="filter-q"><?= e($searchLabel ?? 'Search by title') ?></label>
    <input class="input" type="search" id="filter-q" name="q" value="<?= e($filters['q'] ?? '') ?>" maxlength="100">
  </div>
<?php if (!empty($statuses)): ?>
  <div class="field">
    <label class="field__label" for="filter-status">Status</label>
    <select class="select" id="filter-status" name="status">
      <option value="">All</option>
<?php foreach ($statuses as $sv => $sl): ?><option value="<?= e($sv) ?>"<?= selected(($filters['status'] ?? '') === (string) $sv) ?>><?= e($sl) ?></option><?php endforeach; ?>
    </select>
  </div>
<?php endif; ?>
<?php if (!empty($categories)): ?>
  <div class="field">
    <label class="field__label" for="filter-category">Category</label>
    <select class="select" id="filter-category" name="category">
      <option value="">All</option>
<?php foreach ($categories as $cv => $cl): ?><option value="<?= e((string) $cv) ?>"<?= selected(($filters['category'] ?? '') === (string) $cv) ?>><?= e($cl) ?></option><?php endforeach; ?>
    </select>
  </div>
<?php endif; ?>
  <div class="filters__actions">
    <button type="submit" class="btn btn--sm"><?= icon('search') ?> Filter</button>
<?php if (array_filter($filters, static fn ($x): bool => $x !== '' && $x !== null)): ?><a class="btn btn--secondary btn--sm" href="<?= e(url($action)) ?>">Clear</a><?php endif; ?>
  </div>
</form>
