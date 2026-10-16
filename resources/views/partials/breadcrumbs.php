<?php /** @var list<array{0:string,1:?string}> $breadcrumbs */
if (empty($breadcrumbs)) { return; } $n = count($breadcrumbs); ?>
<nav class="breadcrumbs" aria-label="Breadcrumb">
  <ol>
<?php foreach ($breadcrumbs as $i => [$label, $href]): ?>
    <li><?php if ($href && $i < $n - 1): ?><a href="<?= e(url($href)) ?>"><?= e($label) ?></a><?php else: ?><span aria-current="page"><?= e($label) ?></span><?php endif; ?></li>
<?php endforeach; ?>
  </ol>
</nav>
