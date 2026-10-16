<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\PageCache;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\ImageUploader;

/** @param array<string, string> $query */
function perf_request(string $path, array $query = [], string $method = 'GET', array $server = []): Request
{
    return new Request($method, $path, $query, [], [], $server + ['REMOTE_ADDR' => '127.0.0.1']);
}

test('page cache: only anonymous public GET pages are eligible', function () {
    Config::set('app.page_cache', true);
    Session::reset();
    $_COOKIE = [];
    expect_true(PageCache::eligible(perf_request('/about')));
    expect_true(PageCache::eligible(perf_request('/news', ['page' => '2'])));
    expect_false(PageCache::eligible(perf_request('/news', ['q' => 'skills'])), 'search results are not cached');
    expect_false(PageCache::eligible(perf_request('/admin')));
    expect_false(PageCache::eligible(perf_request('/admin/programs')));
    expect_false(PageCache::eligible(perf_request('/setup')));
    expect_false(PageCache::eligible(perf_request('/webhooks/payments/x', [], 'POST')));
    expect_false(PageCache::eligible(perf_request('/donate/callback', ['reference' => 'RIT-DON-AAAAAAAA'])));
    expect_false(PageCache::eligible(perf_request('/contact', [], 'POST')));
    expect_false(PageCache::eligible(perf_request('/gallery', [], 'GET', ['HTTP_X_REQUESTED_WITH' => 'fetch'])));
    $_COOKIE[(string) config('app.session.name', 'rit_session')] = 'abc';
    expect_false(PageCache::eligible(perf_request('/about')), 'visitors with a session cookie bypass the cache');
    $_COOKIE = [];
    Config::set('app.page_cache', false);
    expect_false(PageCache::eligible(perf_request('/about')), 'disabled by configuration');
});

test('page cache: store, fetch, flush and refusal rules', function () {
    Config::set('app.page_cache', true);
    Session::reset();
    $_COOKIE = [];
    $request = perf_request('/perf-test-page', ['page' => '3']);
    PageCache::flush();
    expect_same(null, PageCache::fetch($request));

    PageCache::store($request, Response::html('<p>cached body</p>'));
    $hit = PageCache::fetch($request);
    expect_true($hit !== null);
    expect_same('<p>cached body</p>', $hit->body());
    expect_same('HIT', $hit->header('X-Page-Cache'));
    expect_same(null, PageCache::fetch(perf_request('/perf-test-page', ['page' => '4'])), 'query string is part of the key');

    PageCache::flush();
    expect_same(null, PageCache::fetch($request), 'flush invalidates everything');

    PageCache::store($request, Response::html('error', 500));
    expect_same(null, PageCache::fetch($request), 'non-200 responses are not stored');
    PageCache::store($request, Response::html('x')->withHeader('Set-Cookie', 'a=b'));
    expect_same(null, PageCache::fetch($request), 'responses setting cookies are not stored');
    PageCache::store($request, Response::json(['a' => 1]));
    expect_same(null, PageCache::fetch($request), 'JSON is not stored');

    Session::start();
    PageCache::store($request, Response::html('form page with a CSRF token'));
    expect_same(null, PageCache::fetch($request), 'pages that started a session are not stored');
    Session::reset();
    PageCache::flush();
    Config::set('app.page_cache', false);
});

test('page cache: a degraded render (database unavailable) is never stored', function () {
    Config::set('app.page_cache', true);
    Session::reset();
    $request = perf_request('/perf-degraded');
    PageCache::markDegraded();
    PageCache::store($request, Response::html('fallback content'));
    expect_same(null, PageCache::fetch($request));
    // Reset the static flag for later tests.
    (new ReflectionProperty(PageCache::class, 'degraded'))->setValue(null, false);
    Config::set('app.page_cache', false);
});

test('srcset lists only existing sizes with their real widths', function () {
    $dir = PUBLIC_ROOT . '/uploads/gallery/2099/12';
    @mkdir($dir, 0755, true);
    $name = str_repeat('ab', 16);
    foreach (['' => 1920, '-md' => 1280, '-thumb' => 720] as $suffix => $width) {
        $img = imagecreatetruecolor($width, (int) round($width * 0.6));
        imagejpeg($img, "{$dir}/{$name}{$suffix}.jpg", 60);
    }
    try {
        $attr = srcset("uploads/gallery/2099/12/{$name}-thumb.jpg", '100vw');
        expect_contains("{$name}-thumb.jpg 720w", $attr);
        expect_contains("{$name}-md.jpg 1280w", $attr);
        expect_contains("{$name}.jpg 1920w", $attr);
        expect_contains('sizes="100vw"', $attr);
        // A second image that has no medium copy (e.g. a small original).
        $other = str_repeat('cd', 16);
        imagejpeg(imagecreatetruecolor(1000, 600), "{$dir}/{$other}.jpg", 60);
        imagejpeg(imagecreatetruecolor(720, 432), "{$dir}/{$other}-thumb.jpg", 60);
        $attr2 = srcset("uploads/gallery/2099/12/{$other}.jpg", '50vw');
        expect_not_contains('-md.jpg', $attr2, 'missing sizes are skipped');
        expect_contains("{$other}.jpg 1000w", $attr2);
        expect_same('', srcset(null));
        expect_same('', srcset('../../etc/passwd'), 'only uploads/ paths');
        expect_same('uploads/x/2026/01/abc-md.webp', ImageUploader::variant('uploads/x/2026/01/abc-thumb.webp', 'md'));
    } finally {
        foreach (glob("{$dir}/*") ?: [] as $f) {
            unlink($f);
        }
        @rmdir($dir);
        @rmdir(dirname($dir));
    }
});

test('asset() serves the minified stylesheet only while it is up to date', function () {
    $css = PUBLIC_ROOT . '/assets/css/main.css';
    $min = PUBLIC_ROOT . '/assets/css/main.min.css';
    if (!is_file($min)) {
        return 'skip';
    }
    $original = filemtime($min);
    touch($min, filemtime($css) + 1);
    clearstatcache();
    expect_contains('/assets/css/main.min.css?v=', asset('css/main.css'));
    touch($min, filemtime($css) - 10);
    clearstatcache();
    expect_contains('/assets/css/main.css?v=', asset('css/main.css'), 'stale minified copy is ignored');
    touch($min, max($original, filemtime($css)));
    clearstatcache();
});

test('the CSP allows exactly the one inline flag script', function () {
    $csp = (string) (new Response('x'))->headers()['Content-Security-Policy'];
    $hash = base64_encode(hash('sha256', Response::JS_FLAG_SCRIPT, true));
    expect_contains("script-src 'self' 'sha256-{$hash}'", $csp);
    expect_not_contains('unsafe-inline', $csp);
});
