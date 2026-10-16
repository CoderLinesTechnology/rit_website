<?php
/** vars: $rows */
$this->layout('layouts/admin');
?>
<?= $this->insert('admin/partials/page-header', [
    'title' => 'Social links',
    'subtitle' => 'Links to the Foundation\'s social media pages, shown in the website header and footer. Only links ticked "Show" appear.',
]) ?>
<?= $this->insert('partials/error-summary') ?>
<form method="post" action="<?= e(url('/admin/social-links')) ?>" data-once>
  <?= csrf_field() ?>
<?php if (!$rows): ?>
<?= $this->insert('admin/partials/empty-state', ['icon' => 'link-45deg', 'title' => 'No social platforms are set up', 'text' => 'The list of platforms is created with the website database.']) ?>
<?php else: ?>
  <div class="table-wrap">
    <table class="table social-table">
      <caption class="visually-hidden">Social media links</caption>
      <thead><tr><th scope="col">Platform</th><th scope="col">Label</th><th scope="col">Address (https://)</th><th scope="col">Show</th><th scope="col">Order</th></tr></thead>
      <tbody>
<?php foreach ($rows as $r): $p = $r['prefix']; $eLabel = field_error($p . '[label]'); $eUrl = field_error($p . '[url]'); $eSort = field_error($p . '[sort_order]'); ?>
        <tr>
          <th scope="row"><?= icon(\App\Models\SocialLink::PLATFORMS[$r['platform']] ?? 'link-45deg') ?> <?= e($r['platformName']) ?></th>
          <td>
            <label class="visually-hidden" for="field-<?= e($p) ?>[label]"><?= e($r['platformName']) ?> label</label>
            <input class="input" type="text" id="field-<?= e($p) ?>[label]" name="<?= e($p) ?>[label]" value="<?= e($r['label']) ?>" maxlength="60" required<?= $eLabel ? ' aria-invalid="true"' : '' ?>>
<?php if ($eLabel): ?><p class="field__error"><?= e($eLabel) ?></p><?php endif; ?>
          </td>
          <td>
            <label class="visually-hidden" for="field-<?= e($p) ?>[url]"><?= e($r['platformName']) ?> address</label>
            <input class="input" type="url" id="field-<?= e($p) ?>[url]" name="<?= e($p) ?>[url]" value="<?= e($r['url']) ?>" maxlength="500" placeholder="https://"<?= $eUrl ? ' aria-invalid="true"' : '' ?>>
<?php if ($eUrl): ?><p class="field__error"><?= e($eUrl) ?></p><?php endif; ?>
          </td>
          <td>
            <label class="checkbox" for="field-<?= e($p) ?>[is_active]"><input type="checkbox" id="field-<?= e($p) ?>[is_active]" name="<?= e($p) ?>[is_active]" value="1"<?= checked($r['is_active']) ?>><span>Show<span class="visually-hidden"> <?= e($r['platformName']) ?> on the website</span></span></label>
          </td>
          <td>
            <label class="visually-hidden" for="field-<?= e($p) ?>[sort_order]"><?= e($r['platformName']) ?> order</label>
            <input class="input input--narrow" type="number" id="field-<?= e($p) ?>[sort_order]" name="<?= e($p) ?>[sort_order]" value="<?= e($r['sort_order']) ?>" min="0" max="9999" step="1"<?= $eSort ? ' aria-invalid="true"' : '' ?>>
<?php if ($eSort): ?><p class="field__error"><?= e($eSort) ?></p><?php endif; ?>
          </td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="form__actions"><button type="submit" class="btn" data-busy-text="Saving...">Save social links</button></div>
<?php endif; ?>
</form>
