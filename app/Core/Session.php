<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Native PHP sessions with hardened cookie settings. The session is started
 * lazily (only pages with forms, flash messages or the admin area need one),
 * so ordinary public pages set no cookie at all.
 */
final class Session
{
    private static bool $started = false;
    private static bool $secure = false;

    /** Flash data from the previous request, read once. @var array<string, mixed> */
    private static array $previousFlash = [];

    public static function configure(bool $secure): void
    {
        self::$secure = $secure;
    }

    public static function start(): void
    {
        if (self::$started) {
            return;
        }
        if (PHP_SAPI === 'cli') {
            // Scripts and unit tests use an in-memory array.
            $_SESSION = $_SESSION ?? [];
            self::$started = true;
            self::rotateFlash();
            return;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.use_trans_sid', '0');
            ini_set('session.cookie_httponly', '1');
            // Many hosts disable PHP's session garbage collection; session files hold
            // flashed form input, so expire them ourselves.
            ini_set('session.gc_probability', '1');
            ini_set('session.gc_divisor', '100');
            ini_set('session.gc_maxlifetime', (string) (((int) config('app.session.absolute_hours', 8)) * 3600));
            $savePath = APP_ROOT . '/storage/sessions';
            if (is_dir($savePath) && is_writable($savePath)) {
                session_save_path($savePath);
            }
            session_name((string) config('app.session.name', 'rit_session'));
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => self::$secure || (bool) config('app.force_https'),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
        self::$started = true;
        self::rotateFlash();
    }

    public static function isStarted(): bool
    {
        return self::$started;
    }

    /** True when the visitor already sent a session cookie (no need to create one). */
    public static function cookiePresent(): bool
    {
        return isset($_COOKIE[(string) config('app.session.name', 'rit_session')]);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /** Store a value for the next request only. */
    public static function flash(string $key, mixed $value): void
    {
        self::start();
        $_SESSION['_flash_next'][$key] = $value;
    }

    /** Read a value flashed by the previous request. */
    public static function getFlash(string $key, mixed $default = null): mixed
    {
        if (!self::$started && !self::cookiePresent()) {
            return $default;
        }
        self::start();
        return self::$previousFlash[$key] ?? $default;
    }

    /** Keep this request's incoming flash for one more request (e.g. after a redirect chain). */
    public static function reflash(): void
    {
        foreach (self::$previousFlash as $key => $value) {
            $_SESSION['_flash_next'][$key] = $value;
        }
    }

    public static function regenerate(): void
    {
        self::start();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'secure' => $params['secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_destroy();
        }
        self::$started = false;
        self::$previousFlash = [];
    }

    /** For tests. */
    public static function reset(): void
    {
        $_SESSION = [];
        self::$started = false;
        self::$previousFlash = [];
    }

    private static function rotateFlash(): void
    {
        self::$previousFlash = is_array($_SESSION['_flash_next'] ?? null) ? $_SESSION['_flash_next'] : [];
        unset($_SESSION['_flash_next']);
    }
}
