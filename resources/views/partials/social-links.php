<?php
/** @var string $variant 'footer' | 'inline' | 'bar' */
$variant = $variant ?? 'inline';
$order = ['facebook', 'linkedin', 'instagram', 'tiktok', 'x', 'youtube', 'whatsapp'];
$names = ['facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'x' => 'X', 'youtube' => 'YouTube', 'whatsapp' => 'WhatsApp'];
$links = [];
foreach (social_links() as $l) { $links[(string) $l['platform']] = $l; }
$sorted = [];
foreach ($order as $p) { if (isset($links[$p])) { $sorted[] = $links[$p]; unset($links[$p]); } }
foreach ($links as $l) { $sorted[] = $l; }
if (!$sorted) { return; }
?>
<ul class="social-list social-list--<?= e($variant) ?>">
<?php foreach ($sorted as $l):
    $p = (string) $l['platform'];
    $label = $names[$p] ?? (string) ($l['label'] ?? ucfirst($p)); ?>
  <li><a href="<?= e($l['url']) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e('RIT Foundation Ghana on ' . $label . ' (opens in a new tab)') ?>"><?= icon($p === 'x' ? 'twitter-x' : $p) ?></a></li>
<?php endforeach; ?>
</ul>
