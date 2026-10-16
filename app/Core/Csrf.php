<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Synchroniser-token CSRF protection. The router verifies the token on every
 * POST route unless the route is explicitly marked withoutCsrf() (only the
 * payment-provider webhook, which is authenticated by its signature instead).
 */
final class Csrf
{
    public const FIELD = '_token';

    public static function token(): string
    {
        $token = Session::get('_csrf');
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            Session::set('_csrf', $token);
        }
        return $token;
    }

    public static function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="' . self::token() . '">';
    }

    public static function verify(Request $request): bool
    {
        if (!Session::cookiePresent() && !Session::isStarted()) {
            return false;
        }
        $expected = Session::get('_csrf');
        // Never from the query string (it would leak through logs and Referer headers).
        $posted = $request->post()[self::FIELD] ?? null;
        $given = is_string($posted) ? $posted : $request->header('X-CSRF-Token');
        return is_string($expected) && strlen($expected) === 64 && hash_equals($expected, $given);
    }

    /** Called after login so a token from before authentication is not reused. */
    public static function rotate(): void
    {
        Session::set('_csrf', bin2hex(random_bytes(32)));
    }
}
