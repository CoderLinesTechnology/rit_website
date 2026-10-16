<?php
/** vars: $article (?array), $v, $action, $categoryOptions, $successCategoryId, $maxMb, $returnTo */
$this->layout('layouts/admin');
$isEdit = $article !== null;
$isLive = $isEdit && $article['status'] === 'published';
?>
<?= $this->insert('admin/partials/page-header', [
    'title' => $isEdit ? 'Edit article' : 'New article',
    'subtitle' => $isEdit ? (string) $article['title'] : 'Fields marked * are required.',
    'actions' => [['label' => 'All articles', 'href' => '/admin/news', 'icon' => 'list', 'variant' => 'secondary']],
]) ?>
<?= $this->insert('partials/error-summary') ?>
<form class="form" method="post" action="<?= e(url($action)) ?>" enctype="multipart/form-data" data-once>
  <?= csrf_field() ?>
  <section class="panel" aria-labelledby="sec-article">
    <h2 class="panel__title" id="sec-article">Article</h2>
    <div class="form-grid form-grid--2">
      <div class="field--wide"><?= $this->insert('partials/form/input', ['name' => 'title', 'label' => 'Title', 'value' => $v['title'] ?? '', 'required' => true, 'maxlength' => 200]) ?></div>
      <?= $this->insert('partials/form/input', ['name' => 'slug', 'label' => 'Web address (slug)', 'value' => $v['slug'] ?? '', 'maxlength' => 220, 'hint' => 'Lowercase letters, numbers and hyphens. Leave empty to create it from the title.', 'attrs' => ['data-slug-source' => '#field-title']]) ?>
      <?= $this->insert('partials/form/select', ['name' => 'category_id', 'label' => 'Category', 'value' => $v['category_id'] ?? '', 'options' => $categoryOptions, 'placeholder' => 'No category']) ?>
      <?= $this->insert('partials/form/input', ['name' => 'author_name', 'label' => 'Author', 'value' => $v['author_name'] ?? '', 'maxlength' => 120, 'hint' => 'Shown as the article byline. Leave empty to show none.']) ?>
      <div class="field--wide"><?= $this->insert('partials/form/textarea', ['name' => 'excerpt', 'label' => 'Short summary', 'value' => $v['excerpt'] ?? '', 'required' => true, 'rows' => 3, 'maxlength' => 500, 'counter' => true, 'hint' => 'Shown in article lists and when the article is shared.']) ?></div>
    </div>
    <?= $this->insert('admin/partials/content-markup-field', ['name' => 'content', 'label' => 'Article text', 'value' => $v['content'] ?? '', 'required' => true, 'rows' => 16, 'maxlength' => 100000]) ?>
  </section>

  <section class="panel" aria-labelledby="sec-image">
    <h2 class="panel__title" id="sec-image">Featured image</h2>
    <?= $this->insert('admin/partials/content-image-field', ['label' => 'Featured image', 'current' => $isEdit ? (string) $article['featured_image_path'] : '', 'alt' => $v['image_alt'] ?? '', 'maxMb' => $maxMb]) ?>
  </section>

  <section class="panel" aria-labelledby="sec-publish">
    <h2 class="panel__title" id="sec-publish">Publishing</h2>
    <div class="form-grid form-grid--2">
      <?= $this->insert('partials/form/select', ['name' => 'status', 'label' => 'Status', 'value' => $v['status'] ?? 'draft', 'required' => true, 'placeholder' => 'Choose a status', 'options' => ['draft' => 'Draft (hidden)', 'published' => 'Published']]) ?>
      <?= $this->insert('partials/form/input', ['name' => 'published_at', 'label' => 'Publication date and time', 'type' => 'datetime-local', 'value' => $v['published_at'] ?? '', 'hint' => 'Ghana time (GMT). Leave empty to publish immediately. A future date schedules the article: it appears on the website at that time.']) ?>
    </div>
    <?= $this->insert('partials/form/checkbox', ['name' => 'is_featured', 'label' => 'Feature this article', 'value' => '1', 'checked' => ($v['is_featured'] ?? '0') === '1']) ?>
    <?= $this->insert('partials/form/checkbox', ['name' => 'consent_confirmed', 'label' => 'The people named or pictured have given permission to publish this story', 'value' => '1', 'checked' => ($v['consent_confirmed'] ?? '0') === '1', 'hint' => $successCategoryId !== null ? 'Required before a story in the "Success stories" category can be published.' : '']) ?>
  </section>

  <section class="panel" aria-labelledby="sec-seo">
    <h2 class="panel__title" id="sec-seo">Search and sharing</h2>
    <div class="form-grid">
      <?= $this->insert('partials/form/input', ['name' => 'seo_title', 'label' => 'Search engine title', 'value' => $v['seo_title'] ?? '', 'maxlength' => 150, 'hint' => 'About 60 characters is ideal. Leave empty to use the article title.']) ?>
      <?= $this->insert('partials/form/textarea', ['name' => 'seo_description', 'label' => 'Search engine description', 'value' => $v['seo_description'] ?? '', 'rows' => 3, 'maxlength' => 300, 'counter' => true, 'hint' => 'About 155 characters is ideal. Leave empty to use the short summary.']) ?>
    </div>
  </section>

  <div class="form__actions">
    <button type="submit" class="btn" data-busy-text="Saving..."><?= $isEdit ? 'Save changes' : 'Save article' ?></button>
    <a class="btn btn--secondary" href="<?= e(url('/admin/news')) ?>">Cancel</a>
  </div>
</form>

<?php if ($isEdit): $aid = (int) $article['id']; ?>
<section class="panel content-narrow" aria-labelledby="sec-actions">
  <h2 class="panel__title" id="sec-actions">Publish or unpublish</h2>
  <p class="muted">These buttons act on the last saved version. Currently: <?= $this->insert('admin/partials/badge', ['status' => $isLive ? 'published' : 'draft']) ?></p>
<?php if ($isLive): ?>
  <form method="post" action="<?= e(url('/admin/news/' . $aid . '/unpublish')) ?>" data-confirm="Unpublish this article? It will disappear from the website and become a draft." data-once>
    <?= csrf_field() ?><input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
    <button type="submit" class="btn btn--secondary">Unpublish</button>
  </form>
<?php else: ?>
  <form method="post" action="<?= e(url('/admin/news/' . $aid . '/publish')) ?>" data-once>
    <?= csrf_field() ?><input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
    <button type="submit" class="btn">Publish now</button>
  </form>
<?php endif; ?>
</section>
<section class="danger-zone" aria-labelledby="sec-danger">
  <h2 id="sec-danger">Delete this article</h2>
  <p>This permanently deletes the article and its image. To hide it without deleting, unpublish it.</p>
<?= $this->insert('admin/partials/content-delete-form', ['action' => '/admin/news/' . $aid . '/delete', 'text' => 'Delete article', 'confirm' => 'Delete the article "' . $article['title'] . '"? This cannot be undone.', 'label' => 'Delete article']) ?>
</section>
<?php endif; ?>
