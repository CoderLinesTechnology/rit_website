<?php /** Plain inner-page header (no photo). */ ?>
<div class="page-header">
  <div class="container">
    <?= $this->insert('partials/breadcrumbs', ['breadcrumbs' => $breadcrumbs ?? []]) ?>
    <h1><?= e($title) ?></h1>
<?php if (!empty($intro)): ?>
    <p class="page-header__intro"><?= e($intro) ?></p>
<?php endif; ?>
  </div>
</div>
