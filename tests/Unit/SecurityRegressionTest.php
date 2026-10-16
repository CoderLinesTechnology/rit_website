<?php

declare(strict_types=1);

use App\Core\Application;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\PageCache;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;

/*
 * One test per finding of the pre-launch security review, so they stay fixed.
 */

test('M1 page cache: only allow-listed query strings are cacheable', function () {
    Config::set('app.page_cache', true);
    Session::reset();
    $_COOKIE = [];
    $req = static fn (array $q): Request => new Request('GET', '/about', $q, [], [], ['REMOTE_ADDR' => '127.0.0.1']);
    expect_true(PageCache::eligible($req([])));
    expect_true(PageCache::eligible($req(['page' => '2'])));
    expect_true(PageCache::eligible($req(['category' => 'fashion-and-creative-skills'])));
    expect_false(PageCache::eligible($req(['x' => bin2hex(random_bytes(4))])), 'random parameters are never stored');
    expect_false(PageCache::eligible($req(['page' => '1'])), 'page=1 duplicates the bare URL');
    expect_false(PageCache::eligible($req(['page' => '007'])));
    expect_false(PageCache::eligible($req(['page' => '5000'])));
    expect_false(PageCache::eligible($req(['category' => '../etc'])));
    Config::set('app.page_cache', false);
});

test('M2 email: addresses carrying mailto parameters are rejected and never linked', function () {
    foreach (['parent.real%40gmail.com?bcc=spy@evil.example', 'a@b.com?cc=x@y.z', 'a@b.com#x', 'a/b@c.com'] as $bad) {
        expect_true(Validator::make(['e' => $bad], ['e' => 'required|email'])->fails(), "rejects {$bad}");
        expect_same('', mailto_url($bad));
    }
    expect_true(Validator::make(['e' => "kofi.o'neil+rit@mail.example.org"], ['e' => 'required|email'])->passes());
    expect_same('mailto:a@b.co?subject=Re%3A%20hi', mailto_url('a@b.co', 'Re: hi'));
});

test('M3 redirects: encoded control characters can never form a //host redirect', function () {
    foreach (['/%09/evil.com/', '/%0d/evil.com/', '/%0a/x/', '/%5C%5Cevil.com/'] as $uri) {
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = (new Application())->handle(Request::fromGlobals());
        $location = (string) $response->header('Location');
        expect_true($location === '' || (str_starts_with($location, '/') && !str_starts_with($location, '//')), "{$uri} → {$location}");
        expect_same(0, preg_match('/[\x00-\x20\x7F\\\\]/', $location), "no control characters or backslashes in Location for {$uri}");
    }
    expect_same('/', Response::redirect("/ok\r\nSet-Cookie: a=b")->header('Location'));
    expect_same('ab', (new Response('', 200, ['X-T' => "a\r\nb"]))->header('X-T'));
    $_SERVER['REQUEST_URI'] = '/';
});

test('L1 passwords: bcrypt cost 12 on every PHP version', function () {
    expect_true(str_starts_with(Auth::hashPassword('Correct-Horse-Battery-9'), '$2y$12$'));
});

test('L2 confirmation greeting never repeats a URL or markup typed into the name', function () {
    $greeting = new ReflectionMethod(App\Services\Notifier::class, 'greeting');
    expect_same("Hello Ama,\n\n", $greeting->invoke(null, 'Ama Serwaa'));
    expect_same("Hello,\n\n", $greeting->invoke(null, 'https://evil.example/login Review'));
    expect_same("Hello,\n\n", $greeting->invoke(null, '<a href=x>Click</a>'));
});

test('L9 CSRF: a token in the query string is not accepted', function () {
    Session::reset();
    $token = Csrf::token();
    $viaQuery = new Request('POST', '/admin/account', ['_token' => $token], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
    expect_false(Csrf::verify($viaQuery));
    $viaBody = new Request('POST', '/admin/account', [], ['_token' => $token], [], ['REMOTE_ADDR' => '127.0.0.1']);
    expect_true(Csrf::verify($viaBody));
    Session::reset();
});

test('L13 configuration: an unexpected APP_ENV is treated as production', function () {
    $previous = getenv('APP_ENV');
    try {
        foreach (['Production', 'prod', 'live'] as $value) {
            putenv('APP_ENV=' . $value);
            $config = require APP_ROOT . '/config/app.php';
            expect_same('production', $config['env'], "APP_ENV={$value}");
            expect_false($config['debug']);
        }
    } finally {
        putenv('APP_ENV=' . ($previous === false ? 'testing' : $previous));
    }
});

test('setup: a SETUP_TOKEN shorter than 32 characters keeps the setup page disabled', function () {
    $previous = getenv('SETUP_TOKEN');
    try {
        putenv('SETUP_TOKEN=short-token-123');
        expect_same(null, (require APP_ROOT . '/config/app.php')['setup_token']);
        putenv('SETUP_TOKEN=' . str_repeat('a1B2', 9));
        expect_same(str_repeat('a1B2', 9), (require APP_ROOT . '/config/app.php')['setup_token']);
    } finally {
        putenv('SETUP_TOKEN' . ($previous === false ? '' : '=' . $previous));
    }
});

test('routes: a trailing newline does not match a route', function () {
    $route = new App\Core\Route('GET', '/about', [stdClass::class, 'x']);
    expect_same(null, $route->match("/about\n"));
    expect_true($route->match('/about') !== null);
});
