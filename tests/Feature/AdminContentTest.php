<?php

declare(strict_types=1);

/*
 * Content-management admin screens (programs, news, gallery, impact, team,
 * settings, social links, SEO).
 *
 * Two styles:
 *  - in-process: a session is created directly (Session + Auth::reset) and
 *    requests go through Application::handle, so routing, permission
 *    middleware and CSRF are real;
 *  - HTTP (php -S) with a real sign-in, needed for file uploads
 *    (is_uploaded_file). These skip with a reason if sign-in is unavailable.
 *
 * Everything created carries the marker "ZZAC" and is removed afterwards.
 */

require_once __DIR__ . '/../http.php';

use App\Core\Application;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\GalleryCategory;
use App\Models\GalleryImage;
use App\Models\ImpactMetric;
use App\Models\Milestone;
use App\Models\NewsArticle;
use App\Models\NewsCategory;
use App\Models\Program;
use App\Models\ProgramCategory;
use App\Models\SeoMeta;
use App\Models\SocialLink;
use App\Models\TeamMember;
use App\Services\ImageUploader;
use App\Services\Settings;

const AC_MARK = 'ZZAC';
const AC_PASSWORD = 'Correct-horse-battery-7-staple';

// ── fixtures ──────────────────────────────────────────────────────────────

function ac_db(): Database
{
    return Database::connection();
}

function ac_app(): Application
{
    static $app = null;
    return $app ??= new Application();
}

/** Removes every row and file the tests created. */
function ac_cleanup(): void
{
    $db = ac_db();
    $paths = [];
    $collect = static function (string $sql) use ($db, &$paths): void {
        foreach ($db->fetchAll($sql) as $row) {
            foreach ($row as $v) {
                if (is_string($v) && str_starts_with($v, 'uploads/')) {
                    $paths[] = $v;
                }
            }
        }
    };
    $m = AC_MARK . '%';
    $collect("SELECT featured_image_path, featured_thumb_path FROM programs WHERE title LIKE '$m'");
    $collect("SELECT image_path, thumb_path FROM program_images WHERE program_id IN (SELECT id FROM programs WHERE title LIKE '$m')");
    $collect("SELECT image_path, thumb_path FROM program_categories WHERE name LIKE '$m'");
    $collect("SELECT featured_image_path, featured_thumb_path FROM news_articles WHERE title LIKE '$m'");
    $collect("SELECT image_path, thumb_path FROM gallery_images WHERE category_id IN (SELECT id FROM gallery_categories WHERE name LIKE '$m')");
    $collect("SELECT image_path, thumb_path FROM impact_metrics WHERE label LIKE '$m'");
    $collect("SELECT photo_path, photo_thumb_path FROM team_members WHERE full_name LIKE '$m'");

    $db->execute("DELETE FROM training_applications WHERE reference LIKE '$m'");
    $db->execute("DELETE FROM programs WHERE title LIKE '$m'");
    $db->execute("DELETE FROM program_categories WHERE name LIKE '$m'");
    $db->execute("DELETE FROM news_articles WHERE title LIKE '$m'");
    $db->execute("DELETE FROM news_categories WHERE name LIKE '$m'");
    $db->execute("DELETE FROM gallery_images WHERE category_id IN (SELECT id FROM gallery_categories WHERE name LIKE '$m')");
    $db->execute("DELETE FROM gallery_categories WHERE name LIKE '$m'");
    $db->execute("DELETE FROM impact_metrics WHERE label LIKE '$m'");
    $db->execute("DELETE FROM team_members WHERE full_name LIKE '$m'");
    $db->execute("DELETE FROM milestones WHERE title LIKE '$m'");
    $db->execute("DELETE FROM admin_audit_logs WHERE summary LIKE '%" . AC_MARK . "%' OR admin_id IN (SELECT id FROM admins WHERE email LIKE 'ac-%@example.test')");
    $db->execute("DELETE FROM admins WHERE email LIKE 'ac-%@example.test'");
    $uploader = new ImageUploader();
    foreach ($paths as $p) {
        $uploader->delete($p);
    }
}

function ac_admin(string $role = 'content_editor'): int
{
    static $n = 0;
    $n++;
    return ac_db()->insert('admins', [
        'name' => 'AC Tester ' . $n,
        'email' => 'ac-' . $role . '-' . $n . '-' . bin2hex(random_bytes(3)) . '@example.test',
        'password_hash' => Auth::hashPassword(AC_PASSWORD),
        'role' => $role,
    ]);
}

/** Signs an administrator into the in-memory session. */
function ac_login(string $role = 'content_editor'): int
{
    $id = ac_admin($role);
    Session::reset();
    Session::start();
    Session::set('_admin', ['id' => $id, 'version' => 1, 'login_at' => time(), 'last_seen' => time()]);
    Auth::reset();
    return $id;
}

/**
 * @param array<string, mixed> $post
 * @param array<string, mixed> $query
 * @param array<string, mixed> $files
 */
function ac_send(string $method, string $path, array $post = [], array $query = [], array $files = [], bool $token = true): Response
{
    $_SERVER['REQUEST_URI'] = $path . ($query ? '?' . http_build_query($query) : '');
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    if ($method === 'POST' && $token) {
        $post['_token'] = Csrf::token();
    }
    return ac_app()->handle(new Request($method, $path, $query, $post, $files, ['REQUEST_URI' => $_SERVER['REQUEST_URI'], 'REMOTE_ADDR' => '127.0.0.1']));
}

/** @param array<string, mixed> $query */
function ac_get(string $path, array $query = []): Response
{
    return ac_send('GET', $path, [], $query);
}

/** @param array<string, mixed> $post */
function ac_post(string $path, array $post = []): Response
{
    return ac_send('POST', $path, $post);
}

/** Behaves like the next request: flashed messages/input become readable. */
function ac_next(): void
{
    $saved = $_SESSION;
    Session::reset();
    $_SESSION = $saved;
    Session::start();
    Auth::reset();
}

/** @return array<string, string> errors flashed by the last redirect */
function ac_errors(): array
{
    return $_SESSION['_flash_next']['_errors'] ?? [];
}

function ac_flash(string $type): ?string
{
    $v = $_SESSION['_flash_next']['_message_' . $type] ?? null;
    return is_string($v) ? $v : null;
}

function ac_clear_flash(): void
{
    unset($_SESSION['_flash_next']);
}

function ac_location(Response $r): string
{
    return (string) $r->header('Location');
}

/** @return array<string, mixed> */
function ac_program_post(array $o = []): array
{
    return $o + [
        'title' => AC_MARK . ' Program ' . bin2hex(random_bytes(3)), 'slug' => '', 'category_id' => '', 'icon' => 'laptop',
        'summary' => 'A short summary.', 'description' => 'The **full** description.', 'status' => 'draft',
        'sort_order' => '5', 'image_alt' => '',
    ];
}

function ac_program(array $o = []): int
{
    static $n = 0;
    $n++;
    return Program::create($o + [
        'title' => AC_MARK . ' Fixture ' . $n . ' ' . bin2hex(random_bytes(3)),
        'slug' => 'zzac-fixture-' . $n . '-' . bin2hex(random_bytes(4)),
        'summary' => 'Summary', 'description' => 'Description', 'status' => 'draft', 'sort_order' => 0,
    ]);
}

function ac_news(array $o = []): int
{
    static $n = 0;
    $n++;
    return NewsArticle::create($o + [
        'title' => AC_MARK . ' Article ' . $n . ' ' . bin2hex(random_bytes(3)),
        'slug' => 'zzac-article-' . $n . '-' . bin2hex(random_bytes(4)),
        'excerpt' => 'Excerpt', 'content' => 'Content', 'status' => 'draft',
    ]);
}

function ac_album(array $o = []): int
{
    static $n = 0;
    $n++;
    return GalleryCategory::create($o + ['name' => AC_MARK . ' Album ' . $n . ' ' . bin2hex(random_bytes(3)), 'slug' => 'zzac-album-' . $n . '-' . bin2hex(random_bytes(4)), 'is_published' => 1]);
}

function ac_photo(int $albumId, array $o = []): int
{
    return GalleryImage::create($o + [
        'category_id' => $albumId, 'image_path' => 'uploads/gallery/2026/01/' . bin2hex(random_bytes(16)) . '.webp',
        'thumb_path' => 'uploads/gallery/2026/01/' . bin2hex(random_bytes(16)) . '-thumb.webp', 'width' => 400, 'height' => 300,
        'alt_text' => 'A test photo', 'is_published' => 0, 'consent_confirmed' => 0, 'sort_order' => 1,
    ]);
}

function ac_success_category(): int
{
    $c = NewsCategory::findBySlug(NewsCategory::SUCCESS_STORIES_SLUG);
    if ($c !== null) {
        return (int) $c['id'];
    }
    return NewsCategory::create(['name' => 'Success stories', 'slug' => NewsCategory::SUCCESS_STORIES_SLUG, 'sort_order' => 1]);
}

function ac_audit_count(string $action): int
{
    return (int) ac_db()->fetchValue('SELECT COUNT(*) FROM admin_audit_logs WHERE action = :a', ['a' => $action]);
}

/** @return array{name: string, type: string, tmp_name: string, error: int, size: int} */
function ac_upload(string $bytes, string $name, string $type = 'image/jpeg'): array
{
    $tmp = sys_get_temp_dir() . '/ac-' . bin2hex(random_bytes(5));
    file_put_contents($tmp, $bytes);
    return ['name' => $name, 'type' => $type, 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($bytes)];
}

function ac_jpeg(int $w = 300, int $h = 200): string
{
    $im = imagecreatetruecolor($w, $h);
    imagefill($im, 0, 0, (int) imagecolorallocate($im, 40, 120, 200));
    ob_start();
    imagejpeg($im, null, 85);
    return (string) ob_get_clean();
}

ac_cleanup();
register_shutdown_function('ac_cleanup');

// ── access control (router middleware) ────────────────────────────────────

test('admin content: content editor can open every content module', function () {
    ac_login('content_editor');
    foreach (['/admin/programs', '/admin/program-categories', '/admin/news', '/admin/news-categories', '/admin/gallery', '/admin/impact', '/admin/team', '/admin/settings', '/admin/social-links', '/admin/seo', '/admin/programs/create', '/admin/news/create', '/admin/gallery/albums/create', '/admin/team/members/create', '/admin/team/milestones/create'] as $path) {
        expect_same(200, ac_get($path)->status(), $path);
    }
});

test('admin content: applications officer gets 403 on content modules (GET and POST)', function () {
    ac_login('applications_officer');
    foreach (['/admin/programs', '/admin/news', '/admin/gallery', '/admin/impact', '/admin/team', '/admin/settings', '/admin/social-links', '/admin/seo'] as $path) {
        expect_same(403, ac_get($path)->status(), $path);
    }
    $before = (int) ac_db()->fetchValue('SELECT COUNT(*) FROM programs');
    expect_same(403, ac_post('/admin/programs', ac_program_post())->status());
    expect_same(403, ac_post('/admin/settings/organization', ['s' => ['org.name' => 'Hacked']])->status());
    expect_same($before, (int) ac_db()->fetchValue('SELECT COUNT(*) FROM programs'));
});

test('admin content: signed-out visitors are sent to the login page', function () {
    Session::reset();
    Auth::reset();
    $r = ac_get('/admin/programs');
    expect_same(303, $r->status());
    expect_same('/admin/login', ac_location($r));
});

test('admin content: a POST without a CSRF token is rejected with 419', function () {
    ac_login('content_editor');
    $before = (int) ac_db()->fetchValue('SELECT COUNT(*) FROM programs');
    $r = ac_send('POST', '/admin/programs', ac_program_post(), [], [], false);
    expect_same(419, $r->status());
    expect_same($before, (int) ac_db()->fetchValue('SELECT COUNT(*) FROM programs'));
});

// ── programs ──────────────────────────────────────────────────────────────

test('admin content: program create stores a row, makes a slug, sets created_by and audits', function () {
    $admin = ac_login();
    $audits = ac_audit_count('program.create');
    $post = ac_program_post(['title' => AC_MARK . ' Web Design & Coding', 'status' => 'published', 'is_featured' => '1', 'accepts_applications' => '1', 'application_opens_on' => '2026-01-01', 'application_closes_on' => '2026-02-01']);
    $r = ac_post('/admin/programs', $post);
    expect_same(303, $r->status());
    $row = ac_db()->fetch('SELECT * FROM programs WHERE title = :t', ['t' => $post['title']]);
    expect_true($row !== null, 'row exists');
    expect_same('zzac-web-design-coding', $row['slug']);
    expect_same($admin, (int) $row['created_by']);
    expect_same($admin, (int) $row['updated_by']);
    expect_same(1, (int) $row['is_featured']);
    expect_same('published', $row['status']);
    expect_same('/admin/programs/' . $row['id'] . '/edit', ac_location($r));
    expect_same($audits + 1, ac_audit_count('program.create'));
});

test('admin content: program slug must be unique and an auto slug gets a numeric suffix', function () {
    ac_login();
    $title = AC_MARK . ' Same Title';
    ac_post('/admin/programs', ac_program_post(['title' => $title]));
    ac_post('/admin/programs', ac_program_post(['title' => $title]));
    $slugs = array_column(ac_db()->fetchAll('SELECT slug FROM programs WHERE title = :t ORDER BY id', ['t' => $title]), 'slug');
    expect_same(['zzac-same-title', 'zzac-same-title-2'], $slugs);

    ac_clear_flash();
    $r = ac_post('/admin/programs', ac_program_post(['title' => AC_MARK . ' Other', 'slug' => 'zzac-same-title']));
    expect_same('/admin/programs/create', ac_location($r));
    expect_contains('already used', ac_errors()['slug'] ?? '');
    expect_same(0, (int) ac_db()->fetchValue("SELECT COUNT(*) FROM programs WHERE title = :t", ['t' => AC_MARK . ' Other']));
});

test('admin content: program validation errors redirect back and keep the input', function () {
    ac_login();
    $cases = [
        'title' => ['title' => ''],
        'summary' => ['summary' => ''],
        'description' => ['description' => ''],
        'slug' => ['slug' => 'Not A Slug!'],
        'icon' => ['icon' => 'skull'],
        'status' => ['status' => 'deleted'],
        'category_id' => ['category_id' => '999999'],
        'sort_order' => ['sort_order' => '10000'],
        'application_closes_on' => ['application_opens_on' => '2026-05-10', 'application_closes_on' => '2026-05-01'],
        'application_opens_on' => ['application_opens_on' => '31/12/2026'],
    ];
    foreach ($cases as $field => $override) {
        ac_clear_flash();
        $post = ac_program_post($override + ['title' => AC_MARK . ' Invalid ' . $field]);
        $r = ac_post('/admin/programs', $post);
        expect_same('/admin/programs/create', ac_location($r), $field);
        expect_true(isset(ac_errors()[$field]), 'error on ' . $field . ' got ' . json_encode(ac_errors()));
        expect_same(0, (int) ac_db()->fetchValue('SELECT COUNT(*) FROM programs WHERE title LIKE :t', ['t' => AC_MARK . ' Invalid%']));
    }
    // The re-rendered form shows the old input and the error.
    ac_clear_flash();
    ac_post('/admin/programs', ac_program_post(['title' => AC_MARK . ' Keep me', 'summary' => '']));
    ac_next();
    $html = ac_get('/admin/programs/create')->body();
    expect_contains(AC_MARK . ' Keep me', $html);
    expect_contains('Short summary is required', $html);
});

test('admin content: program edit and update save changes and set updated_by', function () {
    $admin = ac_login();
    $id = ac_program(['status' => 'draft']);
    $html = ac_get('/admin/programs/' . $id . '/edit')->body();
    expect_contains('Edit program', $html);
    $row = Program::find($id);
    $post = ac_program_post(['title' => $row['title'], 'slug' => $row['slug'], 'status' => 'published', 'summary' => 'New summary', 'duration' => '12 weeks']);
    $r = ac_post('/admin/programs/' . $id, $post);
    expect_same('/admin/programs/' . $id . '/edit', ac_location($r));
    $after = Program::find($id);
    expect_same('published', $after['status']);
    expect_same('New summary', $after['summary']);
    expect_same('12 weeks', $after['duration']);
    expect_same($admin, (int) $after['updated_by']);
    expect_same(404, ac_get('/admin/programs/999999/edit')->status());
    expect_same(404, ac_post('/admin/programs/999999', $post)->status());
});

test('admin content: program edit re-render restores unchecked checkboxes from the failed submission', function () {
    ac_login();
    $id = ac_program(['is_featured' => 1]);
    $row = Program::find($id);
    ac_post('/admin/programs/' . $id, ac_program_post(['title' => $row['title'], 'slug' => $row['slug'], 'summary' => ''])); // no is_featured => unchecked
    ac_next();
    $html = ac_get('/admin/programs/' . $id . '/edit')->body();
    expect_true(preg_match('/<input type="checkbox" id="field-is_featured"[^>]*checked/', $html) !== 1, 'is_featured stays unchecked');
});

test('admin content: program delete removes it, keeps applications (program_id becomes empty) and audits', function () {
    ac_login();
    $id = ac_program();
    $ref = AC_MARK . '-' . bin2hex(random_bytes(3));
    ac_db()->insert('training_applications', [
        'reference' => $ref, 'program_id' => $id, 'program_title' => 'P', 'full_name' => 'A', 'email' => 'a@example.test', 'phone' => '0240000000',
        'age_range' => '18-24', 'is_adult' => 1, 'location' => 'Accra', 'reason' => 'r', 'privacy_accepted_at' => gmdate('Y-m-d H:i:s'),
    ]);
    $audits = ac_audit_count('program.delete');
    $r = ac_post('/admin/programs/' . $id . '/delete');
    expect_same(303, $r->status());
    expect_same(null, Program::find($id));
    $app = ac_db()->fetch('SELECT program_id FROM training_applications WHERE reference = :r', ['r' => $ref]);
    expect_true($app !== null, 'application is kept');
    expect_same(null, $app['program_id']);
    expect_same($audits + 1, ac_audit_count('program.delete'));
    expect_same(404, ac_post('/admin/programs/' . $id . '/delete')->status());
});

test('admin content: a program image can only be deleted through its own program', function () {
    ac_login();
    $a = ac_program();
    $b = ac_program();
    $img = Program::addImage($a, ['image_path' => 'uploads/programs/2026/01/' . str_repeat('a', 32) . '.webp', 'thumb_path' => 'uploads/programs/2026/01/' . str_repeat('a', 32) . '-thumb.webp', 'width' => 10, 'height' => 10, 'alt_text' => 'x']);
    expect_same(404, ac_post('/admin/programs/' . $b . '/images/' . $img . '/delete')->status());
    expect_true(Program::findImage($a, $img) !== null, 'image still exists');
    expect_same(303, ac_post('/admin/programs/' . $a . '/images/' . $img . '/delete')->status());
    expect_same(null, Program::findImage($a, $img));
});

test('admin content: program list filters work and pagination links keep them', function () {
    ac_login();
    $cat = ProgramCategory::create(['name' => AC_MARK . ' Cat', 'slug' => 'zzac-cat-' . bin2hex(random_bytes(3)), 'sort_order' => 0]);
    for ($i = 1; $i <= 22; $i++) {
        ac_program(['title' => AC_MARK . ' Paged ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'status' => 'published', 'category_id' => $cat]);
    }
    ac_program(['title' => AC_MARK . ' Draft one', 'status' => 'draft', 'category_id' => $cat]);
    $html = ac_get('/admin/programs', ['q' => AC_MARK . ' Paged', 'status' => 'published', 'category' => (string) $cat])->body();
    expect_contains('page=2', $html);
    expect_contains('status=published', $html);
    expect_contains('category=' . $cat, $html);
    expect_not_contains('Draft one', $html);
    $page2 = ac_get('/admin/programs', ['q' => AC_MARK . ' Paged', 'status' => 'published', 'page' => '2'])->body();
    expect_contains('Paged 22', $page2);
    expect_not_contains('Paged 01', $page2);
    $draft = ac_get('/admin/programs', ['status' => 'draft', 'q' => AC_MARK . ' Draft'])->body();
    expect_contains('Draft one', $draft);
    $empty = ac_get('/admin/programs', ['q' => 'no-such-' . bin2hex(random_bytes(4))])->body();
    expect_contains('No programs match those filters', $empty);
});

test('admin content: program list escapes a script payload in the title', function () {
    ac_login();
    $payload = AC_MARK . ' <script>alert(1)</script> "x" onmouseover=alert(2)';
    $id = ac_program(['title' => $payload]);
    $html = ac_get('/admin/programs', ['q' => AC_MARK])->body();
    expect_not_contains('<script>alert(1)</script>', $html);
    expect_contains('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    $form = ac_get('/admin/programs/' . $id . '/edit')->body();
    expect_not_contains('<script>alert(1)</script>', $form);
    expect_not_contains('data-confirm="Delete the program "' . AC_MARK . ' <', $html);
});

test('admin content: delete buttons are confirmation forms (programs, news, gallery, impact)', function () {
    ac_login();
    ac_program(['title' => AC_MARK . ' Confirm me']);
    expect_contains('data-confirm="Delete the program', ac_get('/admin/programs', ['q' => AC_MARK . ' Confirm'])->body());
    $a = ac_album();
    ac_photo($a);
    expect_contains('data-confirm="Delete this photo?', ac_get('/admin/gallery/albums/' . $a)->body());
    expect_contains('Applications already received for it are kept', ac_get('/admin/programs', ['q' => AC_MARK . ' Confirm'])->body());
});

// ── program categories ────────────────────────────────────────────────────

test('admin content: program category delete is refused while programs use it', function () {
    ac_login();
    $r = ac_post('/admin/program-categories', ['name' => AC_MARK . ' Skills', 'slug' => '', 'sort_order' => '1', 'icon' => 'palette']);
    expect_same(303, $r->status());
    $cat = ac_db()->fetch('SELECT * FROM program_categories WHERE name = :n', ['n' => AC_MARK . ' Skills']);
    expect_same('zzac-skills', $cat['slug']);
    $p = ac_program(['category_id' => $cat['id']]);
    ac_clear_flash();
    $r = ac_post('/admin/program-categories/' . $cat['id'] . '/delete');
    expect_contains('still has 1 program', ac_flash('error') ?? '');
    expect_true(ProgramCategory::find((int) $cat['id']) !== null, 'category still exists');
    Program::delete($p);
    ac_clear_flash();
    ac_post('/admin/program-categories/' . $cat['id'] . '/delete');
    expect_same(null, ProgramCategory::find((int) $cat['id']));
    // Duplicate slug is rejected.
    ac_post('/admin/program-categories', ['name' => AC_MARK . ' One', 'slug' => 'zzac-dupe', 'sort_order' => '0']);
    ac_clear_flash();
    ac_post('/admin/program-categories', ['name' => AC_MARK . ' Two', 'slug' => 'zzac-dupe', 'sort_order' => '0']);
    expect_true(isset(ac_errors()['slug']), 'duplicate slug refused');
});

// ── news ──────────────────────────────────────────────────────────────────

test('admin content: news publish and unpublish toggle the status and audit', function () {
    $admin = ac_login();
    $id = ac_news();
    $pub = ac_audit_count('news.publish');
    $r = ac_post('/admin/news/' . $id . '/publish', ['return_to' => '/admin/news?status=draft&page=2']);
    expect_same('/admin/news?status=draft&page=2', ac_location($r));
    $row = NewsArticle::find($id);
    expect_same('published', $row['status']);
    expect_true($row['published_at'] !== null && $row['published_at'] <= gmdate('Y-m-d H:i:s'), 'published now');
    expect_same($admin, (int) $row['updated_by']);
    expect_same($pub + 1, ac_audit_count('news.publish'));
    $r = ac_post('/admin/news/' . $id . '/unpublish', ['return_to' => 'https://evil.example/']);
    expect_same('/admin/news', ac_location($r));
    expect_same('draft', NewsArticle::find($id)['status']);
    expect_same(404, ac_post('/admin/news/999999/publish')->status());
});

test('admin content: success stories need permission confirmed before they can be published', function () {
    ac_login();
    $cat = ac_success_category();
    // Via the form.
    $base = ['title' => AC_MARK . ' Story', 'slug' => '', 'category_id' => (string) $cat, 'excerpt' => 'x', 'content' => 'y', 'status' => 'published'];
    $r = ac_post('/admin/news', $base);
    expect_same('/admin/news/create', ac_location($r));
    expect_contains('permission', ac_errors()['consent_confirmed'] ?? '');
    expect_same(0, (int) ac_db()->fetchValue('SELECT COUNT(*) FROM news_articles WHERE title = :t', ['t' => AC_MARK . ' Story']));
    // A draft does not need it.
    ac_clear_flash();
    ac_post('/admin/news', ['title' => AC_MARK . ' Story draft', 'status' => 'draft'] + $base);
    expect_same(1, (int) ac_db()->fetchValue('SELECT COUNT(*) FROM news_articles WHERE title = :t', ['t' => AC_MARK . ' Story draft']));
    // Publish button refuses.
    $draft = (int) ac_db()->fetchValue('SELECT id FROM news_articles WHERE title = :t', ['t' => AC_MARK . ' Story draft']);
    ac_clear_flash();
    ac_post('/admin/news/' . $draft . '/publish');
    expect_same('draft', NewsArticle::find($draft)['status']);
    expect_contains('permission', ac_flash('error') ?? '');
    // With consent it works, in both places.
    ac_clear_flash();
    $r = ac_post('/admin/news', ['consent_confirmed' => '1'] + $base);
    expect_same(303, $r->status());
    $row = ac_db()->fetch('SELECT * FROM news_articles WHERE title = :t', ['t' => AC_MARK . ' Story']);
    expect_same('published', $row['status']);
    expect_same(1, (int) $row['consent_confirmed']);
    ac_db()->execute('UPDATE news_articles SET consent_confirmed = 1 WHERE id = :i', ['i' => $draft]);
    ac_post('/admin/news/' . $draft . '/publish');
    expect_same('published', NewsArticle::find($draft)['status']);
    // Other categories do not need it.
    $other = NewsCategory::create(['name' => AC_MARK . ' News', 'slug' => 'zzac-news-' . bin2hex(random_bytes(3)), 'sort_order' => 0]);
    ac_clear_flash();
    $r = ac_post('/admin/news', ['title' => AC_MARK . ' Plain', 'category_id' => (string) $other] + $base);
    expect_same(1, (int) ac_db()->fetchValue('SELECT COUNT(*) FROM news_articles WHERE title = :t', ['t' => AC_MARK . ' Plain']));
});

test('admin content: publishing without a date uses now, a future date is scheduled and labelled', function () {
    ac_login();
    $base = ['title' => AC_MARK . ' Dated', 'slug' => '', 'category_id' => '', 'excerpt' => 'x', 'content' => 'y', 'status' => 'published'];
    ac_post('/admin/news', $base);
    $row = ac_db()->fetch('SELECT * FROM news_articles WHERE title = :t', ['t' => AC_MARK . ' Dated']);
    expect_true(abs(strtotime($row['published_at'] . ' UTC') - time()) < 120, 'published_at is now');

    $future = gmdate('Y-m-d\TH:i', time() + 86400 * 3);
    ac_post('/admin/news', ['title' => AC_MARK . ' Scheduled', 'published_at' => $future] + $base);
    $row = ac_db()->fetch('SELECT * FROM news_articles WHERE title = :t', ['t' => AC_MARK . ' Scheduled']);
    expect_same(str_replace('T', ' ', $future) . ':00', $row['published_at']);
    $html = ac_get('/admin/news', ['q' => AC_MARK . ' Scheduled'])->body();
    expect_contains('badge badge--scheduled', $html);
    expect_same(null, NewsArticle::findPublishedBySlug($row['slug']), 'not public yet');

    ac_clear_flash();
    $r = ac_post('/admin/news', ['title' => AC_MARK . ' Bad date', 'published_at' => 'tomorrow'] + $base);
    expect_true(isset(ac_errors()['published_at']), 'bad date refused');
});

test('admin content: news category delete is refused while articles use it; success-stories is protected', function () {
    ac_login();
    $cat = NewsCategory::create(['name' => AC_MARK . ' Used', 'slug' => 'zzac-used-' . bin2hex(random_bytes(3)), 'sort_order' => 0]);
    $art = ac_news(['category_id' => $cat]);
    ac_clear_flash();
    ac_post('/admin/news-categories/' . $cat . '/delete');
    expect_contains('used by 1 article', ac_flash('error') ?? '');
    expect_true(NewsCategory::find($cat) !== null, 'still there');
    NewsArticle::delete($art);
    ac_clear_flash();
    ac_post('/admin/news-categories/' . $cat . '/delete');
    expect_same(null, NewsCategory::find($cat));
    $success = ac_success_category();
    ac_clear_flash();
    ac_post('/admin/news-categories/' . $success . '/delete');
    expect_true(NewsCategory::find($success) !== null, 'success stories category cannot be deleted');
    // Its slug cannot be changed.
    ac_post('/admin/news-categories/' . $success, ['name' => 'Success stories', 'slug' => 'something-else', 'sort_order' => '1']);
    expect_same(NewsCategory::SUCCESS_STORIES_SLUG, NewsCategory::find($success)['slug']);
});

test('admin content: news delete removes the article', function () {
    ac_login();
    $id = ac_news();
    ac_post('/admin/news/' . $id . '/delete');
    expect_same(null, NewsArticle::find($id));
});

// ── gallery ───────────────────────────────────────────────────────────────

test('admin content: gallery album create, https-only external link and delete refused while it has photos', function () {
    ac_login();
    $name = AC_MARK . ' Album X';
    $r = ac_post('/admin/gallery/albums', ['name' => $name, 'slug' => '', 'external_url' => 'http://drive.example.org/x', 'is_published' => '1', 'sort_order' => '1']);
    expect_same('/admin/gallery/albums/create', ac_location($r));
    expect_contains('https://', ac_errors()['external_url'] ?? '');
    ac_clear_flash();
    $r = ac_post('/admin/gallery/albums', ['name' => $name, 'slug' => '', 'external_url' => 'https://drive.example.org/x', 'is_published' => '1', 'sort_order' => '1']);
    expect_same(303, $r->status());
    $album = ac_db()->fetch('SELECT * FROM gallery_categories WHERE name = :n', ['n' => $name]);
    expect_same('zzac-album-x', $album['slug']);
    $photo = ac_photo((int) $album['id']);
    ac_clear_flash();
    ac_post('/admin/gallery/albums/' . $album['id'] . '/delete');
    expect_contains('Delete the photos or move them', ac_flash('error') ?? '');
    expect_true(GalleryCategory::find((int) $album['id']) !== null, 'album kept');
    ac_post('/admin/gallery/images/' . $photo . '/delete');
    ac_clear_flash();
    ac_post('/admin/gallery/albums/' . $album['id'] . '/delete');
    expect_same(null, GalleryCategory::find((int) $album['id']));
});

test('admin content: a gallery photo can only be published once permission is confirmed', function () {
    ac_login();
    $a = ac_album();
    $b = ac_album();
    $photo = ac_photo($a);
    $base = ['title' => '', 'alt_text' => 'A photo', 'caption' => '', 'taken_on' => '', 'category_id' => (string) $a, 'sort_order' => '1'];
    $r = ac_post('/admin/gallery/images/' . $photo, ['is_published' => '1'] + $base);
    expect_same('/admin/gallery/images/' . $photo . '/edit', ac_location($r));
    expect_true(isset(ac_errors()['consent_confirmed']), 'consent required');
    expect_same(0, (int) GalleryImage::find($photo)['is_published']);
    ac_clear_flash();
    // Alt text is required.
    ac_post('/admin/gallery/images/' . $photo, ['alt_text' => ''] + $base);
    expect_true(isset(ac_errors()['alt_text']), 'alt required');
    ac_clear_flash();
    // With consent: published, and moved to another album.
    $r = ac_post('/admin/gallery/images/' . $photo, ['is_published' => '1', 'consent_confirmed' => '1', 'is_featured' => '1', 'category_id' => (string) $b] + $base);
    expect_same('/admin/gallery/albums/' . $b, ac_location($r));
    $row = GalleryImage::find($photo);
    expect_same(1, (int) $row['is_published']);
    expect_same(1, (int) $row['is_featured']);
    expect_same($b, (int) $row['category_id']);
    // Unknown album.
    ac_clear_flash();
    ac_post('/admin/gallery/images/' . $photo, ['category_id' => '999999'] + $base);
    expect_true(isset(ac_errors()['category_id']), 'album must exist');
    expect_same(404, ac_get('/admin/gallery/images/999999/edit')->status());
});

test('admin content: gallery upload form validates before any file is read (consent, alt, empty batch)', function () {
    ac_login();
    $a = ac_album();
    $r = ac_send('POST', '/admin/gallery/albums/' . $a . '/images', ['alt_text' => 'x', 'is_published' => '1'], [], ['images' => ['name' => ['a.jpg'], 'type' => ['image/jpeg'], 'tmp_name' => ['/nonexistent'], 'error' => [UPLOAD_ERR_OK], 'size' => [10]]]);
    expect_same('/admin/gallery/albums/' . $a, ac_location($r));
    expect_true(isset(ac_errors()['consent_confirmed']), 'publishing needs consent');
    ac_clear_flash();
    ac_send('POST', '/admin/gallery/albums/' . $a . '/images', ['alt_text' => ''], [], []);
    expect_true(isset(ac_errors()['alt_text']) && isset(ac_errors()['images']), 'alt text and files required');
    expect_same(0, GalleryCategory::imageCount($a));
});

// ── impact ────────────────────────────────────────────────────────────────

test('admin content: an impact metric can only be saved as published when verified', function () {
    $admin = ac_login();
    $base = ['label' => AC_MARK . ' Reach', 'value' => '576', 'is_approximate' => '1', 'status' => 'published', 'sort_order' => '1', 'icon' => 'people-fill'];
    $r = ac_post('/admin/impact', $base);
    expect_same('/admin/impact/create', ac_location($r));
    expect_true(isset(ac_errors()['verified']), 'verification required');
    expect_same(0, (int) ac_db()->fetchValue('SELECT COUNT(*) FROM impact_metrics WHERE label = :l', ['l' => AC_MARK . ' Reach']));
    ac_clear_flash();
    ac_post('/admin/impact', ['status' => 'draft'] + $base);
    $row = ac_db()->fetch('SELECT * FROM impact_metrics WHERE label = :l', ['l' => AC_MARK . ' Reach']);
    expect_same('draft', $row['status']);
    expect_same($admin, (int) $row['created_by']);
    ac_clear_flash();
    $r = ac_post('/admin/impact/' . $row['id'], ['verified' => '1'] + $base);
    expect_same(303, $r->status());
    expect_same('published', ImpactMetric::find((int) $row['id'])['status']);
    // Not stored.
    expect_true(!array_key_exists('verified', ImpactMetric::find((int) $row['id'])), 'verified is only a gate');
    // Value limit and icon allowlist.
    ac_clear_flash();
    ac_post('/admin/impact/' . $row['id'], ['verified' => '1', 'value' => str_repeat('9', 41)] + $base);
    expect_true(isset(ac_errors()['value']), 'value max 40');
    ac_clear_flash();
    ac_post('/admin/impact/' . $row['id'], ['verified' => '1', 'icon' => 'laptop'] + $base);
    expect_true(isset(ac_errors()['icon']), 'icon allowlist');
    ac_post('/admin/impact/' . $row['id'] . '/delete');
    expect_same(null, ImpactMetric::find((int) $row['id']));
});

// ── team and milestones ───────────────────────────────────────────────────

test('admin content: team members and milestones are gated by approval/verification before publishing', function () {
    ac_login();
    $r = ac_post('/admin/team/members', ['full_name' => AC_MARK . ' Ama', 'role_title' => 'Director', 'is_published' => '1', 'sort_order' => '1']);
    expect_same('/admin/team/members/create', ac_location($r));
    expect_true(isset(ac_errors()['consent_confirmed']), 'approval required');
    ac_clear_flash();
    ac_post('/admin/team/members', ['full_name' => AC_MARK . ' Ama', 'role_title' => 'Director', 'is_published' => '1', 'consent_confirmed' => '1', 'sort_order' => '1']);
    $m = ac_db()->fetch('SELECT * FROM team_members WHERE full_name = :n', ['n' => AC_MARK . ' Ama']);
    expect_same(1, (int) $m['is_published']);
    expect_true(in_array((int) $m['id'], array_map(static fn ($x) => (int) $x['id'], TeamMember::published()), true), 'public sees them');
    $audit = ac_db()->fetchValue("SELECT summary FROM admin_audit_logs WHERE action = 'team.member_create' ORDER BY id DESC LIMIT 1");
    expect_not_contains('Ama', (string) $audit, 'audit summary holds no personal name');

    ac_clear_flash();
    $r = ac_post('/admin/team/milestones', ['period_label' => '2021', 'title' => AC_MARK . ' Registered', 'is_published' => '1']);
    expect_same('/admin/team/milestones/create', ac_location($r));
    expect_true(isset(ac_errors()['is_verified']), 'verification required');
    ac_clear_flash();
    ac_post('/admin/team/milestones', ['period_label' => '2021', 'title' => AC_MARK . ' Registered', 'is_published' => '1', 'is_verified' => '1']);
    $ms = ac_db()->fetch('SELECT * FROM milestones WHERE title = :t', ['t' => AC_MARK . ' Registered']);
    expect_same(1, (int) $ms['is_verified']);
    $html = ac_get('/admin/team')->body();
    expect_contains(AC_MARK . ' Ama', $html);
    expect_contains(AC_MARK . ' Registered', $html);
    ac_post('/admin/team/members/' . $m['id'] . '/delete');
    ac_post('/admin/team/milestones/' . $ms['id'] . '/delete');
    expect_same(null, TeamMember::find((int) $m['id']));
    expect_same(null, Milestone::find((int) $ms['id']));
});

// ── settings ──────────────────────────────────────────────────────────────

/** @param list<string> $keys @return array<string, ?string> */
function ac_settings_snapshot(array $keys): array
{
    $snap = [];
    foreach ($keys as $k) {
        $v = ac_db()->fetchValue('SELECT setting_value FROM site_settings WHERE setting_key = :k', ['k' => $k]);
        $snap[$k] = $v === null ? null : (string) $v;
    }
    return $snap;
}

/** @param array<string, ?string> $snap */
function ac_settings_restore(array $snap): void
{
    foreach ($snap as $k => $v) {
        if ($v === null) {
            ac_db()->execute('DELETE FROM site_settings WHERE setting_key = :k', ['k' => $k]);
        } else {
            ac_db()->execute('UPDATE site_settings SET setting_value = :v WHERE setting_key = :k', ['v' => $v, 'k' => $k]);
        }
    }
    Settings::flush();
}

test('admin content: settings tabs and saving follow the per-group permission', function () {
    $snap = ac_settings_snapshot(['donations.notice', 'org.name']);
    try {
        $editor = ac_login('content_editor');
        $html = ac_get('/admin/settings')->body();
        expect_contains('group=organization', $html);
        expect_not_contains('group=donations', $html);
        expect_same(403, ac_get('/admin/settings', ['group' => 'donations'])->status());
        expect_same(403, ac_post('/admin/settings/donations', ['s' => ['donations.notice' => 'Hacked']])->status());
        expect_same(403, ac_post('/admin/settings/legal', ['s' => ['privacy.policy' => 'x']])->status());
        expect_same(404, ac_post('/admin/settings/nonexistent', ['s' => []])->status());
        expect_same(404, ac_get('/admin/settings', ['group' => 'nonexistent'])->status());
        expect_true(Settings::get('donations.notice') !== 'Hacked', 'not saved');

        ac_login('super_admin');
        $html = ac_get('/admin/settings')->body();
        expect_contains('group=donations', $html);
        expect_same(200, ac_get('/admin/settings', ['group' => 'donations'])->status());
        $r = ac_post('/admin/settings/donations', ['s' => ['donations.notice' => 'Updated ' . AC_MARK, 'donations.offline_instructions' => 'Pay by **bank**.']]);
        expect_same(303, $r->status());
        Settings::flush();
        expect_same('Updated ' . AC_MARK, Settings::get('donations.notice'));
    } finally {
        ac_settings_restore($snap);
    }
});

test('admin content: saving a "verify" field validates it, audits the keys (not the values) and shows the badge when empty', function () {
    $keys = ['contact.email', 'contact.phone', 'contact.map_embed_url', 'org.name', 'contact.address', 'contact.postal_address', 'contact.phone_secondary', 'contact.office_hours'];
    $snap = ac_settings_snapshot($keys);
    try {
        ac_login('content_editor');
        $base = [
            'contact.email' => Settings::get('contact.email'), 'contact.phone' => Settings::get('contact.phone'),
            'contact.phone_secondary' => Settings::get('contact.phone_secondary'), 'contact.address' => Settings::get('contact.address'),
            'contact.postal_address' => Settings::get('contact.postal_address'), 'contact.office_hours' => '', 'contact.map_embed_url' => '',
        ];
        // Invalid values are refused and nothing is saved.
        foreach (['contact.email' => 'not-an-email', 'contact.phone' => 'abc', 'contact.map_embed_url' => 'http://maps.example.org/embed'] as $key => $bad) {
            ac_clear_flash();
            $r = ac_post('/admin/settings/contact', ['s' => [$key => $bad] + $base]);
            expect_same('/admin/settings?group=contact', ac_location($r), $key);
            expect_true(isset(ac_errors()['s[' . $key . ']']), 'error for ' . $key . ' ' . json_encode(ac_errors()));
        }
        expect_true(Settings::get('contact.email') !== 'not-an-email');

        // Empty required-less verify field shows the badge.
        ac_clear_flash();
        $audits = ac_audit_count('settings.update');
        ac_post('/admin/settings/contact', ['s' => ['contact.email' => '', 'contact.address' => ''] + $base]);
        Settings::flush();
        expect_same('', Settings::get('contact.email'));
        $html = ac_get('/admin/settings', ['group' => 'contact'])->body();
        expect_contains('Needs confirmation', $html);

        // A real save by a content editor; the audit summary names keys, not values.
        ac_clear_flash();
        $secret = 'secret-' . bin2hex(random_bytes(4)) . '@example.test';
        $r = ac_post('/admin/settings/contact', ['s' => ['contact.email' => $secret] + $base]);
        expect_same(303, $r->status());
        Settings::flush();
        expect_same($secret, Settings::get('contact.email'));
        $summary = (string) ac_db()->fetchValue("SELECT summary FROM admin_audit_logs WHERE action = 'settings.update' ORDER BY id DESC LIMIT 1");
        expect_contains('contact.email', $summary);
        expect_not_contains($secret, $summary);
        expect_true(ac_audit_count('settings.update') > $audits, 'audited');
        $row = ac_db()->fetch("SELECT updated_by FROM site_settings WHERE setting_key = 'contact.email'");
        expect_true($row['updated_by'] !== null, 'updated_by recorded');

        // Unchanged save says so and writes nothing.
        ac_clear_flash();
        $before = ac_audit_count('settings.update');
        ac_post('/admin/settings/contact', ['s' => ['contact.email' => $secret] + $base]);
        expect_same($before, ac_audit_count('settings.update'));
        expect_same('Nothing was changed.', ac_flash('info'));
    } finally {
        ac_settings_restore($snap);
    }
});

test('admin content: a required setting cannot be emptied and a checkbox setting saves as 0/1', function () {
    $snap = ac_settings_snapshot(['org.name', 'applications.enabled', 'applications.closed_message', 'applications.allow_attachment']);
    try {
        ac_login('content_editor');
        ac_clear_flash();
        ac_post('/admin/settings/organization', ['s' => ['org.name' => '']]);
        expect_contains('Organization name is required', ac_errors()['s[org.name]'] ?? '');
        $keep = ['applications.closed_message' => Settings::get('applications.closed_message')];
        ac_clear_flash();
        ac_post('/admin/settings/applications', ['s' => $keep]); // checkboxes unticked
        Settings::flush();
        expect_same('0', Settings::get('applications.enabled'));
        ac_post('/admin/settings/applications', ['s' => $keep + ['applications.enabled' => '1']]);
        Settings::flush();
        expect_same('1', Settings::get('applications.enabled'));
    } finally {
        ac_settings_restore($snap);
    }
});

// ── social links ──────────────────────────────────────────────────────────

test('admin content: social links accept only https links on real domains and save in one POST', function () {
    $orig = SocialLink::all();
    try {
        ac_login('content_editor');
        $post = [];
        foreach ($orig as $r) {
            $post[$r['id']] = ['label' => $r['label'], 'url' => $r['url'], 'sort_order' => (string) $r['sort_order'], 'is_active' => '1'];
        }
        $first = (int) $orig[0]['id'];
        foreach (['http://www.facebook.com/x', 'javascript:alert(1)', 'https://localhost/x', 'https://user:pw@facebook.com/x', 'ftp://x.example.org', 'not a url', 'https://x'] as $bad) {
            ac_clear_flash();
            $try = $post;
            $try[$first]['url'] = $bad;
            $r = ac_post('/admin/social-links', ['links' => $try]);
            expect_same('/admin/social-links', ac_location($r), $bad);
            expect_true(isset(ac_errors()['links[' . $first . '][url]']), 'refused: ' . $bad);
            expect_same($orig[0]['url'], SocialLink::find($first)['url'], 'unchanged after ' . $bad);
        }
        ac_clear_flash();
        $try = $post;
        $try[$first]['label'] = '';
        ac_post('/admin/social-links', ['links' => $try]);
        expect_true(isset(ac_errors()['links[' . $first . '][label]']), 'label required');

        ac_clear_flash();
        $try = $post;
        $try[$first] = ['label' => 'Our Facebook', 'url' => 'https://www.facebook.com/ritfoundation', 'sort_order' => '9', 'is_active' => '0'];
        $r = ac_post('/admin/social-links', ['links' => $try, 'platform' => 'hacked']);
        expect_same(303, $r->status());
        $row = SocialLink::find($first);
        expect_same('Our Facebook', $row['label']);
        expect_same('https://www.facebook.com/ritfoundation', $row['url']);
        expect_same(0, (int) $row['is_active']);
        expect_same(9, (int) $row['sort_order']);
        expect_same($orig[0]['platform'], $row['platform'], 'platform is fixed');
        expect_contains('facebook', (string) ac_db()->fetchValue("SELECT summary FROM admin_audit_logs WHERE action = 'social_links.update' ORDER BY id DESC LIMIT 1"));
    } finally {
        foreach ($orig as $r) {
            SocialLink::update((int) $r['id'], ['label' => $r['label'], 'url' => $r['url'], 'sort_order' => $r['sort_order'], 'is_active' => $r['is_active']]);
        }
        Settings::flush();
    }
});

// ── SEO ───────────────────────────────────────────────────────────────────

test('admin content: SEO saves a page row, enforces limits and records updated_by', function () {
    $row = SeoMeta::all()[0];
    $rid = (int) $row['id'];
    try {
        $admin = ac_login('content_editor');
        $html = ac_get('/admin/seo')->body();
        expect_contains(SeoMeta::PAGES[$row['page_key']], $html);
        expect_contains('about 60 characters', strtolower(str_replace('About', 'about', $html)));
        ac_clear_flash();
        $r = ac_post('/admin/seo/' . $rid, ['meta_title' => str_repeat('t', 151), 'meta_description' => 'ok']);
        expect_same('/admin/seo?row=' . $rid . '#seo-' . $rid, ac_location($r));
        expect_true(isset(ac_errors()['meta_title']), 'title max 150');
        ac_clear_flash();
        ac_post('/admin/seo/' . $rid, ['meta_title' => str_repeat('d', 10), 'meta_description' => str_repeat('d', 301)]);
        expect_true(isset(ac_errors()['meta_description']), 'description max 300');
        ac_clear_flash();
        $r = ac_post('/admin/seo/' . $rid, ['meta_title' => 'Hello <b>world</b>', 'meta_description' => 'A description']);
        expect_same(303, $r->status());
        $after = SeoMeta::find($rid);
        expect_same('Hello <b>world</b>', $after['meta_title']);
        expect_same($admin, (int) $after['updated_by']);
        $page = ac_get('/admin/seo')->body();
        expect_not_contains('Hello <b>world</b>', $page);
        expect_contains('Hello &lt;b&gt;world&lt;/b&gt;', $page);
        expect_same(404, ac_post('/admin/seo/999999', ['meta_title' => 'x'])->status());
    } finally {
        ac_db()->execute('UPDATE seo_meta SET meta_title = :t, meta_description = :d, og_image_path = :o, updated_by = NULL WHERE id = :i', ['t' => $row['meta_title'], 'd' => $row['meta_description'], 'o' => $row['og_image_path'], 'i' => $rid]);
        SeoMeta::flush();
    }
});

// ── real sign-in over HTTP (uploads) ──────────────────────────────────────

/** @return CookieJar|null a signed-in jar, or null when the login screen is not usable */
function ac_http_login(string $role = 'content_editor'): ?CookieJar
{
    static $cache = [];
    if (array_key_exists($role, $cache)) {
        return $cache[$role];
    }
    $id = ac_admin($role);
    $email = (string) ac_db()->fetchValue('SELECT email FROM admins WHERE id = :i', ['i' => $id]);
    reset_rate_limits();
    $jar = new CookieJar();
    $page = Http::get('/admin/login', $jar);
    $token = csrf_from($page->body);
    if ($page->status !== 200 || $token === '') {
        return $cache[$role] = null;
    }
    $res = Http::post('/admin/login', ['_token' => $token, 'email' => $email, 'password' => AC_PASSWORD], $jar);
    if ($res->status !== 303) {
        return $cache[$role] = null;
    }
    $check = Http::get('/admin/programs', $jar);
    return $cache[$role] = ($check->status === 200 ? $jar : null);
}

/** @return array{0: CookieJar, 1: string}|null jar and CSRF token for a form page */
function ac_http_form(string $path): ?array
{
    $jar = ac_http_login();
    if ($jar === null) {
        return null;
    }
    $page = Http::get($path, $jar);
    return [$jar, csrf_from($page->body)];
}

/** is_file() without PHP's stat cache (the web server deletes files in another process). */
function ac_file(string $relative): bool
{
    clearstatcache();
    return is_file(APP_ROOT . '/public/' . $relative);
}

function ac_tmp_image(string $kind = 'jpeg'): string
{
    $path = sys_get_temp_dir() . '/ac-img-' . bin2hex(random_bytes(5)) . '.' . ($kind === 'jpeg' ? 'jpg' : 'txt');
    file_put_contents($path, $kind === 'jpeg' ? ac_jpeg() : "this is not an image <?php echo 1; ?>\n");
    return $path;
}

test('admin content: program featured image upload (HTTP) needs alt text, rejects non-images, replaces and removes files', function () {
    $ctx = ac_http_form('/admin/programs/create');
    if ($ctx === null) {
        return 'skip';
    }
    [$jar, $token] = $ctx;
    $title = AC_MARK . ' Uploaded';
    $base = ['_token' => $token, 'title' => $title, 'slug' => '', 'summary' => 's', 'description' => 'd', 'status' => 'draft', 'sort_order' => '0'];

    // Image without alt text is refused and nothing is stored.
    $res = Http::post('/admin/programs', $base + ['image' => new CURLFile(ac_tmp_image(), 'image/jpeg', 'photo.jpg')], $jar);
    expect_same(303, $res->status);
    expect_same('/admin/programs/create', $res->header('location'));
    expect_same(0, (int) ac_db()->fetchValue('SELECT COUNT(*) FROM programs WHERE title = :t', ['t' => $title]));

    // A text file renamed .jpg is rejected with a field error.
    $res = Http::post('/admin/programs', $base + ['image_alt' => 'Alt', 'image' => new CURLFile(ac_tmp_image('text'), 'image/jpeg', 'photo.jpg')], $jar);
    expect_same('/admin/programs/create', $res->header('location'));
    $page = Http::get('/admin/programs/create', $jar)->body;
    expect_contains('Only JPEG, PNG or WebP', $page);
    expect_same(0, (int) ac_db()->fetchValue('SELECT COUNT(*) FROM programs WHERE title = :t', ['t' => $title]));

    // A real JPEG.
    $page = Http::get('/admin/programs/create', $jar);
    $res = Http::post('/admin/programs', ['_token' => csrf_from($page->body)] + $base + ['image_alt' => 'A classroom', 'image' => new CURLFile(ac_tmp_image(), 'image/jpeg', 'photo.jpg')], $jar);
    expect_same(303, $res->status);
    $row = ac_db()->fetch('SELECT * FROM programs WHERE title = :t', ['t' => $title]);
    expect_true($row !== null && $row['featured_image_path'] !== null, 'image stored');
    expect_same('A classroom', $row['featured_image_alt']);
    expect_true(ac_file($row['featured_image_path']), 'main file exists');
    expect_true(ac_file($row['featured_thumb_path']), 'thumb exists');
    $oldMain = $row['featured_image_path'];
    $oldThumb = $row['featured_thumb_path'];

    // Replacement deletes the old files.
    $edit = '/admin/programs/' . $row['id'] . '/edit';
    $page = Http::get($edit, $jar);
    $post = ['_token' => csrf_from($page->body), 'title' => $title, 'slug' => $row['slug'], 'summary' => 's', 'description' => 'd', 'status' => 'draft', 'sort_order' => '0', 'image_alt' => 'New alt'];
    $res = Http::post('/admin/programs/' . $row['id'], $post + ['image' => new CURLFile(ac_tmp_image(), 'image/jpeg', 'new.jpg')], $jar);
    expect_same(303, $res->status);
    $new = Program::find((int) $row['id']);
    expect_true($new['featured_image_path'] !== $oldMain, 'path changed');
    expect_true(!ac_file($oldMain) && !ac_file($oldThumb), 'old files deleted');
    expect_true(ac_file($new['featured_image_path']), 'new file exists');

    // Removing the image deletes the files and clears the columns.
    $page = Http::get($edit, $jar);
    $res = Http::post('/admin/programs/' . $row['id'], ['_token' => csrf_from($page->body), 'remove_image' => '1'] + $post, $jar);
    $gone = Program::find((int) $row['id']);
    expect_same(null, $gone['featured_image_path']);
    expect_same(null, $gone['featured_image_alt']);
    expect_true(!ac_file($new['featured_image_path']), 'removed file deleted');

    // Deleting a program deletes its files too.
    $page = Http::get($edit, $jar);
    Http::post('/admin/programs/' . $row['id'], ['_token' => csrf_from($page->body), 'image_alt' => 'Again', 'image' => new CURLFile(ac_tmp_image(), 'image/jpeg', 'again.jpg')] + $post, $jar);
    $with = Program::find((int) $row['id']);
    $file = $with['featured_image_path'];
    expect_true(ac_file($file), 'file before delete');
    $page = Http::get($edit, $jar);
    Http::post('/admin/programs/' . $row['id'] . '/delete', ['_token' => csrf_from($page->body)], $jar);
    expect_same(null, Program::find((int) $row['id']));
    expect_true(!ac_file($file), 'file removed with the program');
});

test('admin content: program additional images and gallery photos report per-file results (HTTP)', function () {
    $jar = ac_http_login();
    if ($jar === null) {
        return 'skip';
    }
    $pid = ac_program();
    $page = Http::get('/admin/programs/' . $pid . '/edit', $jar);
    $token = csrf_from($page->body);
    $res = Http::post('/admin/programs/' . $pid . '/images', [
        '_token' => $token, 'image_batch_alt' => 'Students at work',
        'images[0]' => new CURLFile(ac_tmp_image(), 'image/jpeg', 'ok.jpg'),
        'images[1]' => new CURLFile(ac_tmp_image('text'), 'image/jpeg', 'bad.jpg'),
    ], $jar);
    expect_same(303, $res->status);
    $images = Program::images($pid);
    expect_same(1, count($images));
    expect_same('Students at work', $images[0]['alt_text']);
    $after = Http::get('/admin/programs/' . $pid . '/edit', $jar)->body;
    expect_contains('1 image uploaded', $after);
    expect_contains('1 rejected: bad.jpg', $after);

    // Gallery: needs consent to publish; unpublished upload works and reports results.
    $album = ac_album();
    $page = Http::get('/admin/gallery/albums/' . $album, $jar);
    $token = csrf_from($page->body);
    $files = ['images[0]' => new CURLFile(ac_tmp_image(), 'image/jpeg', 'a.jpg'), 'images[1]' => new CURLFile(ac_tmp_image(), 'image/jpeg', 'b.jpg'), 'images[2]' => new CURLFile(ac_tmp_image('text'), 'image/jpeg', 'c.jpg')];
    $res = Http::post('/admin/gallery/albums/' . $album . '/images', ['_token' => $token, 'alt_text' => 'Photo', 'is_published' => '1'] + $files, $jar);
    expect_same(0, GalleryCategory::imageCount($album), 'publish without consent stores nothing');
    $page = Http::get('/admin/gallery/albums/' . $album, $jar);
    $res = Http::post('/admin/gallery/albums/' . $album . '/images', ['_token' => csrf_from($page->body), 'alt_text' => 'Photo', 'consent_confirmed' => '1', 'is_published' => '1'] + $files, $jar);
    expect_same(303, $res->status);
    $rows = GalleryImage::forAlbum($album);
    expect_same(2, count($rows));
    expect_same(1, (int) $rows[0]['is_published']);
    expect_same(1, (int) $rows[0]['consent_confirmed']);
    expect_true((int) $rows[1]['sort_order'] > (int) $rows[0]['sort_order'], 'sort order increments');
    $html = Http::get('/admin/gallery/albums/' . $album, $jar)->body;
    expect_contains('2 photos uploaded and published', $html);
    expect_contains('1 rejected: c.jpg', $html);
    $path = $rows[0]['image_path'];
    expect_true(ac_file($path), 'photo file exists');
    $page = Http::get('/admin/gallery/images/' . $rows[0]['id'] . '/edit', $jar);
    Http::post('/admin/gallery/images/' . $rows[0]['id'] . '/delete', ['_token' => csrf_from($page->body)], $jar);
    expect_true(!ac_file($path), 'photo file deleted with the record');
});

test('admin content: a settings image can be uploaded and removed (HTTP) and needs its description', function () {
    $jar = ac_http_login('super_admin');
    if ($jar === null) {
        return 'skip';
    }
    $keys = ['site.cta_image', 'site.cta_image_alt', 'site.logo_alt', 'site.logo_path', 'site.share_image', 'footer.text', 'footer.strapline'];
    $snap = ac_settings_snapshot($keys);
    try {
        $form = static function () use ($jar): array {
            $page = Http::get('/admin/settings?group=branding', $jar);
            return [csrf_from($page->body), $page->body];
        };
        $base = static fn (array $over = []): array => $over + ['s[site.logo_alt]' => 'RIT', 's[site.cta_image_alt]' => '', 's[footer.text]' => 'Footer', 's[footer.strapline]' => 'Strap'];
        [$token] = $form();
        // Missing description.
        $res = Http::post('/admin/settings/branding', ['_token' => $token] + $base(['img_site_cta_image' => new CURLFile(ac_tmp_image(), 'image/jpeg', 'cta.jpg')]), $jar);
        expect_same(303, $res->status);
        Settings::flush();
        expect_same('', Settings::get('site.cta_image'));
        // With description.
        [$token] = $form();
        Http::post('/admin/settings/branding', ['_token' => $token] + $base(['s[site.cta_image_alt]' => 'People smiling', 'img_site_cta_image' => new CURLFile(ac_tmp_image(), 'image/jpeg', 'cta.jpg')]), $jar);
        Settings::flush();
        $path = Settings::get('site.cta_image');
        expect_true($path !== '' && ac_file($path), 'uploaded');
        // Remove.
        [$token] = $form();
        Http::post('/admin/settings/branding', ['_token' => $token, 'rm_site_cta_image' => '1'] + $base(['s[site.cta_image_alt]' => 'People smiling']), $jar);
        Settings::flush();
        expect_same('', Settings::get('site.cta_image'));
        expect_true(!ac_file($path), 'file deleted on remove');
    } finally {
        $now = ac_db()->fetchValue("SELECT setting_value FROM site_settings WHERE setting_key = 'site.cta_image'");
        if (is_string($now) && $now !== '') {
            (new ImageUploader())->deleteImage($now, null);
        }
        ac_settings_restore($snap);
    }
});
