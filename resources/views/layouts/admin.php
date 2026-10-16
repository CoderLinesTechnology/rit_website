<?php
/** vars: $title, $newSubmissions (int), $roleLabel (string), $activeNav (string key) */
$user = auth_user() ?? [];
$new = (int) ($newSubmissions ?? 0);
$active = (string) ($activeNav ?? '');
$logo = (string) setting('site.logo_path', '');
$motto = trim((string) setting('org.motto', ''));
// [label, icon, key|null, href|null, permission|null, children [[label, href, key, permission]]]
$menu = [
    ['Dashboard', 'speedometer2', 'dashboard', '/admin', null, []],
    ['Programs', 'mortarboard', 'programs', null, 'content.manage', [['All programs', '/admin/programs', 'programs'], ['Categories', '/admin/program-categories', 'program-categories']]],
    ['Applications', 'file-earmark-text', 'applications', null, 'submissions.manage', [['Training applications', '/admin/applications', 'applications'], ['Volunteers', '/admin/volunteers', 'volunteers'], ['Partnerships', '/admin/partnerships', 'partnerships'], ['Contact messages', '/admin/contacts', 'contacts']]],
    ['News & Stories', 'journal-text', 'news', null, 'content.manage', [['Articles', '/admin/news', 'news'], ['Categories', '/admin/news-categories', 'news-categories']]],
    ['Gallery', 'images', 'gallery', null, 'content.manage', [['Albums & photos', '/admin/gallery', 'gallery']]],
    ['Impact', 'bar-chart', 'impact', null, 'content.manage', [['Impact metrics', '/admin/impact', 'impact'], ['Team & milestones', '/admin/team', 'team']]],
    ['Donations', 'cash-coin', 'donations', '/admin/donations', 'donations.view', []],
    ['Users', 'people', 'admins', null, null, [['Administrators', '/admin/admins', 'admins', 'admins.manage'], ['Audit log', '/admin/audit-log', 'audit-log', 'audit.view'], ['Email log', '/admin/email-log', 'email-log', 'admins.manage']]],
    ['Settings', 'gear', 'settings', null, 'settings.content', [['Website content', '/admin/settings', 'settings'], ['Social links', '/admin/social-links', 'social-links'], ['SEO', '/admin/seo', 'seo']]],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e(($title ?? 'Admin') . ' | RIT Foundation admin') ?></title>
<link rel="icon" href="/assets/images/favicon.svg" type="image/svg+xml">
<link rel="preload" href="/assets/fonts/inter-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
<?= $this->section('head') ?>
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>
<div class="admin">
  <header class="topbar">
    <button type="button" class="topbar__menu" aria-controls="admin-sidebar" aria-expanded="true"><?= icon('list') ?> Menu</button>
    <a class="topbar__brand" href="<?= e(url('/admin')) ?>">
<?php if ($logo !== ''): ?>
      <img src="<?= e(upload_url($logo)) ?>" alt="<?= e((string) setting('site.logo_alt', 'RIT Foundation Ghana')) ?>" height="48">
<?php else: ?>
      <span>RIT Foundation Ghana</span>
<?php if ($motto !== ''): ?><small><?= e($motto) ?></small><?php endif; ?>
<?php endif; ?>
    </a>
<?php if (can('submissions.manage')): ?>
    <a class="topbar__bell" href="<?= e(url('/admin/applications', ['status' => 'new'])) ?>" aria-label="<?= e($new > 0 ? $new . ' new submissions' : 'Submissions') ?>"><?= icon('inboxes') ?><?php if ($new > 0): ?><span class="count-pill" aria-hidden="true"><?= $new > 99 ? '99+' : $new ?></span><?php endif; ?></a>
<?php endif; ?>
    <details class="usermenu">
      <summary>
        <span class="usermenu__avatar"><?= icon('person-badge') ?></span>
        <span class="usermenu__who"><strong><?= e($user['name'] ?? '') ?></strong><span><?= e((string) ($roleLabel ?? ucfirst(str_replace('_', ' ', (string) ($user['role'] ?? ''))))) ?></span></span>
        <span class="visually-hidden">Account menu</span>
      </summary>
      <div class="usermenu__panel">
        <a href="<?= e(url('/admin/account')) ?>"><?= icon('gear') ?> My account</a>
        <a href="<?= e(url('/')) ?>"><?= icon('globe2') ?> View website</a>
        <form method="post" action="<?= e(url('/admin/logout')) ?>"><?= csrf_field() ?><button type="submit"><?= icon('box-arrow-right') ?> Log out</button></form>
      </div>
    </details>
  </header>
  <div class="admin__body">
    <aside class="sidebar" id="admin-sidebar">
      <nav class="sidebar__nav" aria-label="Admin">
        <ul class="snav">
<?php foreach ($menu as [$label, $ico, $key, $href, $perm, $children]):
    if ($perm !== null && !can($perm)) { continue; }
    $kids = array_values(array_filter($children, static fn(array $c): bool => !isset($c[3]) || can($c[3])));
    if ($children && !$kids) { continue; }
    $childActive = false;
    foreach ($kids as $c) { if ($c[2] === $active) { $childActive = true; } }
    $isActive = $childActive || (!$children && $key === $active);
    $pill = ($key === 'applications' && $new > 0);
    ?>
          <li<?= $isActive ? ' class="is-active"' : '' ?>>
<?php if (!$kids): ?>
            <a href="<?= e(url($href)) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>><?= icon($ico) ?><span><?= e($label) ?></span></a>
<?php else: ?>
            <details<?= $childActive ? ' open' : '' ?>>
              <summary><?= icon($ico) ?><span><?= e($label) ?></span><?php if ($pill): ?><span class="count-pill"><span class="visually-hidden">New: </span><?= $new > 99 ? '99+' : $new ?></span><?php endif; ?><?= icon('chevron-right', 'icon chev') ?></summary>
              <ul class="snav__sub">
<?php foreach ($kids as $c): ?>
                <li><a href="<?= e(url($c[1])) ?>"<?= $c[2] === $active ? ' aria-current="page"' : '' ?>><?= e($c[0]) ?></a></li>
<?php endforeach; ?>
              </ul>
            </details>
<?php endif; ?>
          </li>
<?php endforeach; ?>
        </ul>
        <form class="sidebar__logout-form" method="post" action="<?= e(url('/admin/logout')) ?>"><?= csrf_field() ?><button type="submit" class="sidebar__logout"><?= icon('box-arrow-right') ?> Logout</button></form>
      </nav>
    </aside>
    <div class="admin__main">
      <main class="content" id="main" tabindex="-1">
<?= $this->insert('partials/flash') ?>
<?= $this->section('content') ?>
      </main>
      <footer class="admin-footer">
        <span>RIT Foundation Ghana Admin Dashboard</span>
        <span><a href="<?= e(url('/privacy')) ?>">Privacy Policy</a> | <a href="<?= e(url('/terms')) ?>">Terms &amp; Conditions</a> | <a href="<?= e(url('/admin/help')) ?>">Help</a></span>
      </footer>
    </div>
  </div>
</div>
<?= $this->section('scripts') ?>
</body>
</html>
