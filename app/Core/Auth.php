<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Administrator authentication and role-based permissions.
 *
 * - Passwords: password_hash()/password_verify(), rehashed when the default
 *   algorithm or cost changes.
 * - Sign-in is throttled per account+IP and per IP; failures never reveal
 *   whether the email address exists or the account is disabled.
 * - The session id is regenerated on sign-in and the CSRF token rotated.
 * - Sessions expire after an idle period and an absolute lifetime, and are
 *   revoked everywhere when the admin's session_version changes (password
 *   change, deactivation, role change).
 */
final class Auth
{
    public const ROLES = [
        'super_admin' => 'Super administrator',
        'content_editor' => 'Content editor',
        'applications_officer' => 'Applications officer',
    ];

    /** permission => roles that hold it */
    private const PERMISSIONS = [
        'content.manage' => ['super_admin', 'content_editor'],
        'submissions.manage' => ['super_admin', 'applications_officer'],
        'donations.view' => ['super_admin'],
        'settings.content' => ['super_admin', 'content_editor'],
        'settings.system' => ['super_admin'],
        'admins.manage' => ['super_admin'],
        'audit.view' => ['super_admin'],
    ];

    public const MAX_ATTEMPTS_PER_ACCOUNT = 5;
    public const MAX_ATTEMPTS_PER_IP = 20;
    public const LOCKOUT_SECONDS = 900;
    public const PASSWORD_MIN = 12;
    public const PASSWORD_MAX = 72; // bcrypt only uses the first 72 bytes

    /** Dummy hash so unknown emails take as long as known ones. */
    private const TIMING_HASH = '$2y$12$Q1ZpQ0JzQ2hOb1N1Y2hVc2VyLu0fnNnR9b3O7r0V6N2v1R3H5J6aW';

    /** @var array<string, mixed>|null|false false = not yet resolved this request */
    private static array|null|false $user = false;

    private static ?string $expiredReason = null;

    /** @return 'ok'|'invalid'|'throttled' */
    public static function attempt(string $email, string $password, Request $request): string
    {
        $email = strtolower(trim($email));
        $accountKey = 'login:account:' . $email . '|' . $request->ip();
        $ipKey = 'login:ip:' . $request->ip();

        if (RateLimiter::tooManyAttempts($accountKey, self::MAX_ATTEMPTS_PER_ACCOUNT)
            || RateLimiter::tooManyAttempts($ipKey, self::MAX_ATTEMPTS_PER_IP)) {
            AuditLog::record('auth.login_throttled', null, null, 'Sign-in blocked by rate limit for ' . self::maskEmail($email));
            return 'throttled';
        }

        $admin = Database::connection()->fetch(
            'SELECT id, name, email, password_hash, role, is_active, session_version FROM admins WHERE email = :email',
            ['email' => $email]
        );

        $valid = password_verify($password, $admin['password_hash'] ?? self::TIMING_HASH);
        if (!$valid || $admin === null || (int) $admin['is_active'] !== 1) {
            RateLimiter::hit($accountKey, self::LOCKOUT_SECONDS);
            RateLimiter::hit($ipKey, self::LOCKOUT_SECONDS);
            AuditLog::record('auth.login_failed', 'admin', $admin !== null ? (int) $admin['id'] : null, 'Failed sign-in for ' . self::maskEmail($email), null);
            return 'invalid';
        }

        RateLimiter::clear($accountKey);
        if (password_needs_rehash((string) $admin['password_hash'], PASSWORD_BCRYPT, self::HASH_OPTIONS)) {
            Database::connection()->update('admins', (int) $admin['id'], ['password_hash' => self::hashPassword($password)]);
        }
        self::login($admin);
        return 'ok';
    }

    /** @param array<string, mixed> $admin */
    public static function login(array $admin): void
    {
        Session::regenerate();
        Csrf::rotate();
        $now = time();
        Session::set('_admin', [
            'id' => (int) $admin['id'],
            'version' => (int) $admin['session_version'],
            'login_at' => $now,
            'last_seen' => $now,
        ]);
        Database::connection()->execute('UPDATE admins SET last_login_at = UTC_TIMESTAMP() WHERE id = :id', ['id' => (int) $admin['id']]);
        self::$user = false;
        AuditLog::record('auth.login', 'admin', (int) $admin['id'], 'Signed in', (int) $admin['id']);
    }

    public static function logout(): void
    {
        $id = self::id();
        if ($id !== null) {
            AuditLog::record('auth.logout', 'admin', $id, 'Signed out', $id);
        }
        Session::destroy();
        self::$user = null;
    }

    /** @return array<string, mixed>|null The signed-in, active administrator. */
    public static function user(): ?array
    {
        if (self::$user !== false) {
            return self::$user;
        }
        self::$user = null;
        if (!Session::isStarted() && !Session::cookiePresent()) {
            return null;
        }
        $state = Session::get('_admin');
        if (!is_array($state) || !isset($state['id'], $state['version'], $state['login_at'], $state['last_seen'])) {
            return null;
        }

        $now = time();
        $idle = ((int) config('app.session.idle_minutes', 30)) * 60;
        $absolute = ((int) config('app.session.absolute_hours', 8)) * 3600;
        if ($now - (int) $state['last_seen'] > $idle || $now - (int) $state['login_at'] > $absolute) {
            self::expire('Your session expired. Please sign in again.');
            return null;
        }

        $admin = Database::connection()->fetch(
            'SELECT id, name, email, role, is_active, session_version, last_login_at, password_changed_at FROM admins WHERE id = :id',
            ['id' => (int) $state['id']]
        );
        if ($admin === null || (int) $admin['is_active'] !== 1 || (int) $admin['session_version'] !== (int) $state['version']) {
            self::expire('Please sign in again.');
            return null;
        }

        $state['last_seen'] = $now;
        Session::set('_admin', $state);
        return self::$user = $admin;
    }

    public static function id(): ?int
    {
        $user = self::$user === false ? self::user() : self::$user;
        return $user === null ? null : (int) $user['id'];
    }

    public static function can(string $permission): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }
        return in_array((string) $user['role'], self::PERMISSIONS[$permission] ?? [], true);
    }

    /** @return list<string> */
    public static function rolesWith(string $permission): array
    {
        return self::PERMISSIONS[$permission] ?? [];
    }

    /** Message explaining why the previous session ended, if any. */
    public static function expiredReason(): ?string
    {
        return self::$expiredReason;
    }

    /** Checks a candidate password against the policy; returns an error message or null. */
    public static function passwordProblem(string $password, string $email = '', string $name = ''): ?string
    {
        if (mb_strlen($password) < self::PASSWORD_MIN) {
            return 'Password must be at least ' . self::PASSWORD_MIN . ' characters.';
        }
        if (strlen($password) > self::PASSWORD_MAX) {
            return 'Password must be no more than ' . self::PASSWORD_MAX . ' characters.';
        }
        $lower = strtolower($password);
        if ($email !== '' && str_contains($lower, strtolower((string) strstr($email, '@', true)))) {
            return 'Password must not contain your email address.';
        }
        if ($name !== '' && mb_strlen($name) >= 4 && str_contains($lower, strtolower($name))) {
            return 'Password must not contain your name.';
        }
        if (count(array_unique(str_split($password))) < 6) {
            return 'Password is too repetitive. Use a longer passphrase with varied characters.';
        }
        $common = ['password', 'qwerty', '123456', 'admin', 'letmein', 'welcome', 'ritfoundation', 'foundation', 'ghana'];
        foreach ($common as $word) {
            if (str_contains($lower, $word) && mb_strlen($password) < 16) {
                return 'Password is too easy to guess. Use a longer passphrase.';
            }
        }
        return null;
    }

    /** bcrypt cost 12 on every PHP version (8.2/8.3 default to 10), matching TIMING_HASH. */
    public const HASH_OPTIONS = ['cost' => 12];

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, self::HASH_OPTIONS);
    }

    /** For tests: forget the per-request cache. */
    public static function reset(): void
    {
        self::$user = false;
        self::$expiredReason = null;
    }

    private static function expire(string $reason): void
    {
        Session::forget('_admin');
        Session::regenerate();
        self::$expiredReason = $reason;
        Session::flash('_message_info', $reason);
    }

    private static function maskEmail(string $email): string
    {
        $at = strpos($email, '@');
        if ($at === false || $at === 0) {
            return '***';
        }
        return mb_substr($email, 0, 1) . '***' . substr($email, $at);
    }
}
