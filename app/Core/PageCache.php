<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Full-page cache for anonymous visitors, stored as files in
 * storage/cache/pages. A cached page is served before the router runs, so it
 * needs no database work at all.
 *
 * What is cached: GET/HEAD requests for public pages, from visitors who send
 * no session cookie, answered with 200, without a session being started and
 * without Set-Cookie. Never cached: /admin, /setup, webhooks, the donation
 * callback, search results (?q=), fragment requests, and any page rendered
 * while the database was unavailable (it would freeze fallback content).
 *
 * Invalidation: every successful admin POST (and CLI imports) bumps a
 * generation number, which makes all stored pages stale at once. Edits are
 * rare on this site, so a whole-site flush keeps the hit rate high while
 * guaranteeing nobody sees outdated content. Entries also expire after
 * TTL_SECONDS so scheduled news appears on time.
 */
final class PageCache
{
    public const TTL_SECONDS = 600;
    /** Upper bound of stored pages per generation, so random URLs cannot fill the disk. */
    public const MAX_ENTRIES = 5000;
    private const EXCLUDED_PREFIXES = ['/admin', '/setup', '/webhooks', '/donate/callback'];

    private static bool $degraded = false;

    public static function enabled(): bool
    {
        return (bool) config('app.page_cache', false);
    }

    /** Called when a page had to fall back because the database failed. */
    public static function markDegraded(): void
    {
        self::$degraded = true;
    }

    public static function eligible(Request $request): bool
    {
        if (!self::enabled() || !in_array($request->method(), ['GET', 'HEAD'], true)) {
            return false;
        }
        if (Session::cookiePresent() || $request->wantsFragment()) {
            return false;
        }
        // Only the query parameters real pages use: ?page=2..1000 and ?category=<slug>.
        // Anything else (e.g. ?x=random, or a search) is rendered normally but never stored.
        foreach ($request->queryAll() as $key => $value) {
            $ok = is_string($value) && match ($key) {
                'page' => ctype_digit($value) && $value !== '1' && $value[0] !== '0' && (int) $value <= 1000,
                'category' => preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) === 1 && strlen($value) <= 170,
                default => false,
            };
            if (!$ok) {
                return false;
            }
        }
        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if ($request->path() === $prefix || str_starts_with($request->path(), $prefix . '/')) {
                return false;
            }
        }
        return true;
    }

    public static function fetch(Request $request): ?Response
    {
        $file = self::file($request);
        if (!is_file($file)) {
            return null;
        }
        if (filemtime($file) < time() - self::TTL_SECONDS) {
            @unlink($file);
            return null;
        }
        $data = json_decode((string) @file_get_contents($file), true);
        if (!is_array($data) || !isset($data['body'], $data['type']) || !is_string($data['body'])) {
            return null;
        }
        return (new Response($data['body'], 200, ['Content-Type' => (string) $data['type']]))->withHeader('X-Page-Cache', 'HIT');
    }

    public static function store(Request $request, Response $response): void
    {
        $type = (string) ($response->header('Content-Type') ?? '');
        if (self::$degraded || $response->status() !== 200 || Session::isStarted() || $response->header('Set-Cookie') !== null
            || !preg_match('#^(text/html|application/xml|text/plain)#', $type)
            || str_contains((string) $response->header('Cache-Control'), 'no-store')) {
            return;
        }
        $file = self::file($request);
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            return;
        }
        // Count new entries per generation; stop storing once the cap is reached.
        if (!is_file($file)) {
            $counter = self::root() . '/g' . self::generation() . '.count';
            $count = (int) @file_get_contents($counter);
            if ($count >= self::MAX_ENTRIES) {
                return;
            }
            @file_put_contents($counter, (string) ($count + 1), LOCK_EX);
        }
        $tmp = $dir . '/.tmp-' . bin2hex(random_bytes(6));
        if (@file_put_contents($tmp, (string) json_encode(['type' => $type, 'body' => $response->body()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false) {
            @rename($tmp, $file);
        }
        @unlink($tmp);
    }

    /** Makes every cached page stale (after any content change). */
    public static function flush(): void
    {
        $root = self::root();
        if (!is_dir($root) && !@mkdir($root, 0750, true) && !is_dir($root)) {
            return;
        }
        $old = self::generation();
        @file_put_contents($root . '/generation', (string) ($old + 1), LOCK_EX);
        self::removeDirectory($root . '/g' . $old);
        @unlink($root . '/g' . $old . '.count');
    }

    private static function file(Request $request): string
    {
        $query = $request->queryAll();
        ksort($query);
        $key = hash('sha256', $request->path() . '?' . http_build_query($query));
        return self::root() . '/g' . self::generation() . '/' . substr($key, 0, 2) . '/' . $key . '.json';
    }

    private static function root(): string
    {
        return APP_ROOT . '/storage/cache/pages';
    }

    private static function generation(): int
    {
        $value = @file_get_contents(self::root() . '/generation');
        return $value === false ? 1 : max(1, (int) $value);
    }

    private static function removeDirectory(string $dir): void
    {
        if (!is_dir($dir) || !str_starts_with($dir, self::root() . '/g')) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
            $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
        }
        @rmdir($dir);
    }
}
