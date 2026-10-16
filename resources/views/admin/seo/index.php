<?php
/** vars: $rows, $activeRow, $maxMb */
$this->layout('layouts/admin');
?>
<?= $this->insert('admin/partials/page-header', [
    'title' => 'Search & sharing (SEO)',
    'subtitle' => 'What appears in Google results and when a page is shared on WhatsApp, Facebook and other apps.',
]) ?>
<div class="alert alert--info" role="note"><?= icon('info-circle') ?><p>Leave a field empty and the website uses the page's own title and description instead. Each page below is saved on its own.</p></div>
<?php if (!$rows): ?>
<?= $this->insert('admin/partials/empty-state', ['icon' => 'search', 'title' => 'No pages are set up for SEO', 'text' => 'The list of pages is created with the website database.']) ?>
<?php endif; ?>
<?php foreach ($rows as $r): $rid = (int) $r['id']; $isActive = $rid === (int) $activeRow;
    $eTitle = $isActive ? field_error('meta_title') : null;
    $eDesc = $isActive ? field_error('meta_description') : null;
    $eImg = $isActive ? field_error('image') : null;
    $vTitle = $isActive ? old('meta_title', (string) $r['meta_title']) : (string) $r['meta_title'];
    $vDesc = $isActive ? old('meta_description', (string) $r['meta_description']) : (string) $r['meta_description'];
    $img = upload_url((string) $r['og_image_path']);
    ?>
<section class="panel seo-card" id="seo-<?= $rid ?>" aria-labelledby="seo-<?= $rid ?>-h">
  <h2 id="seo-<?= $rid ?>-h"><?= e($r['page_name']) ?></h2>
  <form class="form" method="post" action="<?= e(url('/admin/seo/' . $rid)) ?>" enctype="multipart/form-data" data-once>
    <?= csrf_field() ?>
    <div class="field<?= $eTitle ? ' field--invalid' : '' ?>">
      <label class="field__label" for="seo-<?= $rid ?>-title">Search title <span class="field__opt">(optional)</span></label>
      <p class="field__hint" id="seo-<?= $rid ?>-title-hint">About 60 characters is ideal. Longer titles may be cut off in search results.</p>
      <textarea class="textarea textarea--short" id="seo-<?= $rid ?>-title" name="meta_title" rows="2" maxlength="150" data-counter="1" aria-describedby="seo-<?= $rid ?>-title-hint"<?= $eTitle ? ' aria-invalid="true"' : '' ?>><?= e($vTitle) ?></textarea>
<?php if ($eTitle): ?><p class="field__error"><?= e($eTitle) ?></p><?php endif; ?>
    </div>
    <div class="field<?= $eDesc ? ' field--invalid' : '' ?>">
      <label class="field__label" for="seo-<?= $rid ?>-desc">Search description <span class="field__opt">(optional)</span></label>
      <p class="field__hint" id="seo-<?= $rid ?>-desc-hint">About 155 characters is ideal.</p>
      <textarea class="textarea" id="seo-<?= $rid ?>-desc" name="meta_description" rows="3" maxlength="300" data-counter="1" aria-describedby="seo-<?= $rid ?>-desc-hint"<?= $eDesc ? ' aria-invalid="true"' : '' ?>><?= e($vDesc) ?></textarea>
<?php if ($eDesc): ?><p class="field__error"><?= e($eDesc) ?></p><?php endif; ?>
    </div>
    <div class="field<?= $eImg ? ' field--invalid' : '' ?>">
      <label class="field__label" for="seo-<?= $rid ?>-image">Share image <span class="field__opt">(optional)</span></label>
      <p class="field__hint" id="seo-<?= $rid ?>-image-hint">Shown when the page is shared. A landscape photo works best. JPEG, PNG or WebP, up to <?= (int) $maxMb ?> MB.</p>
<?php if ($img !== ''): ?><img class="img-preview" src="<?= e($img) ?>" alt="Current share image for <?= e($r['page_name']) ?>"><?php endif; ?>
      <input type="file" id="seo-<?= $rid ?>-image" name="image" accept="image/jpeg,image/png,image/webp" data-preview="#seo-<?= $rid ?>-preview" aria-describedby="seo-<?= $rid ?>-image-hint"<?= $eImg ? ' aria-invalid="true"' : '' ?>>
      <img class="img-preview" id="seo-<?= $rid ?>-preview" alt="Preview of the selected share image" hidden>
<?php if ($eImg): ?><p class="field__error"><?= e($eImg) ?></p><?php endif; ?>
<?php if ($img !== ''): ?>
      <label class="checkbox" for="seo-<?= $rid ?>-remove"><input type="checkbox" id="seo-<?= $rid ?>-remove" name="remove_image" value="1"><span>Remove the current share image</span></label>
<?php endif; ?>
    </div>
    <div class="form__actions"><button type="submit" class="btn" data-busy-text="Saving...">Save <?= e($r['page_name']) ?></button></div>
  </form>
</section>
<?php endforeach; ?>
