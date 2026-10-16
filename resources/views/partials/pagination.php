<?php
/** @var object $paginator page, lastPage, total, url(int):string */
$cur = (int) $paginator->page;
$last = (int) $paginator->lastPage;
if ($last <= 1) { return; }
$pages = [1];
for ($i = $cur - 1; $i <= $cur + 1; $i++) { if ($i > 1 && $i < $last) { $pages[] = $i; } }
$pages[] = $last;
$pages = array_values(array_unique($pages));
?>
<nav class="pagination" aria-label="Pagination">
  <ul>
    <li><?php if ($cur > 1): ?><a href="<?= e($paginator->url($cur - 1)) ?>" rel="prev" aria-label="Previous page"><?= icon('chevron-left') ?></a><?php else: ?><span class="pagination__item is-disabled" aria-hidden="true"><?= icon('chevron-left') ?></span><?php endif; ?></li>
<?php $prev = 0; foreach ($pages as $n): ?>
<?php if ($prev && $n - $prev > 1): ?>    <li><span class="pagination__item pagination__gap" aria-hidden="true">&hellip;</span></li>
<?php endif; ?>
    <li><a href="<?= e($paginator->url($n)) ?>"<?= $n === $cur ? ' aria-current="page"' : '' ?> aria-label="<?= e('Page ' . $n) ?>"><?= $n ?></a></li>
<?php $prev = $n; endforeach; ?>
    <li><?php if ($cur < $last): ?><a href="<?= e($paginator->url($cur + 1)) ?>" rel="next" aria-label="Next page"><?= icon('chevron-right') ?></a><?php else: ?><span class="pagination__item is-disabled" aria-hidden="true"><?= icon('chevron-right') ?></span><?php endif; ?></li>
  </ul>
</nav>
