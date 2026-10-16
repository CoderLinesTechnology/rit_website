<?php /** vars: $items [[label, href, active]], ?$search ['action','value','placeholder'], ?$label */ ?>
<div class="filter-bar">
  <nav aria-label="<?= e($label ?? 'Filter') ?>">
    <ul class="filter-pills">
<?php foreach ($items as [$l, $h, $active]): ?>
      <li><a href="<?= e($h) ?>"<?= $active ? ' aria-current="true"' : '' ?>><?= e($l) ?></a></li>
<?php endforeach; ?>
    </ul>
  </nav>
<?php if (!empty($search)): ?>
  <form class="search-form" role="search" method="get" action="<?= e(url($search['action'])) ?>">
    <label class="visually-hidden" for="filter-q">Search</label>
    <input id="filter-q" type="search" name="q" value="<?= e($search['value'] ?? '') ?>" placeholder="<?= e($search['placeholder'] ?? 'Search…') ?>" maxlength="100">
    <button type="submit" aria-label="Search"><?= icon('search') ?></button>
  </form>
<?php endif; ?>
</div>
