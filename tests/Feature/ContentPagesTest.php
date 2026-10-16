<?php

declare(strict_types=1);

require_once __DIR__ . '/../http.php';

use App\Core\Database;
use App\Models\GalleryCategory;
use App\Models\GalleryImage;
use App\Models\NewsArticle;

/** Removes every row this test file creates. */
function cp_cleanup(): void
{
    $db = Database::connection();
    $db->execute("DELETE FROM gallery_images WHERE title LIKE 'CPTEST%'");
    $db->execute("DELETE FROM gallery_categories WHERE slug LIKE 'cptest-%'");
    $db->execute("DELETE FROM news_articles WHERE slug LIKE 'cptest-%'");
}

/** @param array<string, mixed> $over */
function cp_article(string $slug, array $over = []): int
{
    return NewsArticle::create($over + [
        'category_id' => null, 'title' => 'CPTEST ' . $slug, 'slug' => $slug, 'excerpt' => 'Excerpt', 'content' => 'Body text',
        'status' => 'published', 'published_at' => gmdate('Y-m-d H:i:s', time() - 3600), 'is_featured' => 0, 'consent_confirmed' => 0,
    ]);
}

test('content pages: every public page answers 200', function (): void {
    foreach (['/about', '/impact', '/get-involved', '/privacy', '/terms', '/gallery', '/news', '/news/category/success-stories', '/news?q=skills', '/sitemap.xml', '/robots.txt'] as $path) {
        expect_same(200, Http::get($path)->status, $path);
    }
    $slug = (string) Database::connection()->fetchValue("SELECT slug FROM programs WHERE status = 'published' LIMIT 1");
    expect_same(200, Http::get('/programs/' . $slug)->status);
});

test('content pages: unknown slugs are 404', function (): void {
    foreach (['/gallery/nope-nope', '/news/nope-nope', '/news/category/nope-nope', '/programs/nope-nope'] as $path) {
        expect_same(404, Http::get($path)->status, $path);
    }
});

test('content pages: drafts, scheduled articles and unpublished photos are not visible', function (): void {
    cp_cleanup();
    try {
        cp_article('cptest-draft', ['status' => 'draft', 'published_at' => null]);
        cp_article('cptest-future', ['published_at' => gmdate('Y-m-d H:i:s', time() + 86400)]);
        cp_article('cptest-live');
        expect_same(404, Http::get('/news/cptest-draft')->status);
        expect_same(404, Http::get('/news/cptest-future')->status);
        expect_same(200, Http::get('/news/cptest-live')->status);
        $sitemap = Http::get('/sitemap.xml')->body;
        expect_contains('/news/cptest-live', $sitemap);
        expect_not_contains('cptest-draft', $sitemap);
        expect_not_contains('cptest-future', $sitemap);
        expect_not_contains('CPTEST cptest-draft', Http::get('/news?q=CPTEST')->body);

        $album = GalleryCategory::create(['name' => 'CPTEST album', 'slug' => 'cptest-album', 'description' => '', 'is_published' => 1, 'sort_order' => 99]);
        $hidden = GalleryCategory::create(['name' => 'CPTEST hidden', 'slug' => 'cptest-hidden', 'description' => '', 'is_published' => 0, 'sort_order' => 99]);
        $img = static fn (int $cat, string $title, int $pub): int => GalleryImage::create([
            'category_id' => $cat, 'title' => $title, 'image_path' => 'uploads/gallery/2026/10/' . str_repeat('a', 32) . '.jpg',
            'thumb_path' => 'uploads/gallery/2026/10/' . str_repeat('a', 32) . '-thumb.jpg', 'width' => 100, 'height' => 100,
            'alt_text' => 'alt', 'is_published' => $pub, 'consent_confirmed' => 1, 'sort_order' => 1,
        ]);
        $img($album, 'CPTEST visible photo', 1);
        $img($album, 'CPTEST unpublished photo', 0);
        $img($hidden, 'CPTEST photo in hidden album', 1);
        $page = Http::get('/gallery/cptest-album')->body;
        expect_contains('CPTEST visible photo', $page);
        expect_not_contains('CPTEST unpublished photo', $page);
        expect_not_contains('CPTEST photo in hidden album', Http::get('/gallery')->body);
        expect_same(404, Http::get('/gallery/cptest-hidden')->status);
        expect_not_contains('cptest-hidden', Http::get('/sitemap.xml')->body);
        expect_contains('/gallery/cptest-album', Http::get('/sitemap.xml')->body);
    } finally {
        cp_cleanup();
    }
});

test('content pages: article text is escaped', function (): void {
    cp_cleanup();
    try {
        $payload = '<script>alert(1)</script><img src=x onerror=alert(2)>';
        cp_article('cptest-xss', ['title' => 'CPTEST ' . $payload, 'content' => $payload, 'excerpt' => $payload, 'author_name' => $payload]);
        foreach (['/news/cptest-xss', '/news', '/news?q=CPTEST'] as $path) {
            $body = Http::get($path)->body;
            expect_not_contains('<script>alert(1)', $body, $path);
            expect_not_contains('<img src=x', $body, $path);
        }
        $article = Http::get('/news/cptest-xss')->body;
        expect_true(preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $article, $m) === 1);
        $json = json_decode($m[1], true);
        expect_same('NewsArticle', $json['@type']);
    } finally {
        cp_cleanup();
    }
});

test('content pages: search with SQL-like input is safe', function (): void {
    expect_same(200, Http::get('/news?q=' . rawurlencode("' OR 1=1 --"))->status);
    expect_same(200, Http::get('/news?q=' . rawurlencode('%_\\'))->status);
    expect_same(200, Http::get('/news?q=' . str_repeat('a', 500))->status);
    expect_same(200, Http::get('/news?page=999')->status);
    expect_same(200, Http::get('/gallery?page=-5')->status);
});

test('content pages: sitemap is valid XML and lists programs', function (): void {
    $res = Http::get('/sitemap.xml');
    expect_contains('application/xml', (string) $res->header('content-type'));
    $xml = simplexml_load_string($res->body);
    expect_true($xml !== false, 'sitemap parses');
    $slug = (string) Database::connection()->fetchValue("SELECT slug FROM programs WHERE status = 'published' LIMIT 1");
    expect_contains('/programs/' . $slug, $res->body);
    expect_true(count($xml->url) >= 14);
});

test('content pages: robots.txt disallows everything outside production', function (): void {
    $res = Http::get('/robots.txt');
    expect_contains('text/plain', (string) $res->header('content-type'));
    expect_same("User-agent: *\nDisallow: /\n", $res->body);
});

test('content pages: canonical has no query string except page', function (): void {
    $canonical = static function (string $path): string {
        preg_match('#<link rel="canonical" href="([^"]+)"#', Http::get($path)->body, $m);
        return (string) ($m[1] ?? '');
    };
    expect_true(!str_contains($canonical('/news?q=skills&utm_source=x'), '?'), 'search canonical is the listing');
    expect_true(str_ends_with($canonical('/news?q=skills&utm_source=x'), '/news'));
    expect_true(str_ends_with($canonical('/news?utm_source=x'), '/news'));
    expect_true(str_ends_with($canonical('/about?utm_source=x'), '/about'));
    expect_true(!str_contains($canonical('/gallery?utm_campaign=y'), 'utm'));
    cp_cleanup();
    try {
        for ($i = 1; $i <= 8; $i++) {
            cp_article('cptest-p' . $i, ['published_at' => gmdate('Y-m-d H:i:s', time() - 100 * $i)]);
        }
        $body = Http::get('/news?page=2&utm_source=x')->body;
        preg_match('#<link rel="canonical" href="([^"]+)"#', $body, $m);
        expect_true(str_ends_with($m[1], '/news?page=2'), 'paginated canonical keeps page only: ' . ($m[1] ?? ''));
        expect_contains('Page 2', $body);
    } finally {
        cp_cleanup();
    }
});

test('content pages: no inline scripts or style attributes', function (): void {
    foreach (['/', '/about', '/impact', '/get-involved', '/privacy', '/terms', '/gallery', '/news'] as $path) {
        $body = Http::get($path)->body;
        // The only inline script allowed is the CSP-hashed "js" flag.
        $withoutFlag = str_replace('<script>' . App\Core\Response::JS_FLAG_SCRIPT . '</script>', '', $body);
        expect_same(0, preg_match('#<script(?![^>]*(?:src=|type="application/ld\+json"))#', $withoutFlag), $path . ' inline script');
        expect_same(0, preg_match('#\sstyle="#', $body), $path . ' style attribute');
    }
});
