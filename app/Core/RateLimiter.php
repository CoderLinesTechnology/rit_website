<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Fixed-window counters stored in MySQL (works on any shared host).
 * Keys are HMAC-hashed with APP_KEY, so no raw IP address or email address
 * is stored.
 */
final class RateLimiter
{
    public static function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        $row = Database::connection()->fetch(
            'SELECT hits FROM rate_limits WHERE bucket = :b AND reset_at > UTC_TIMESTAMP()',
            ['b' => app_hash($key)]
        );
        return $row !== null && (int) $row['hits'] >= $maxAttempts;
    }

    /** Records one attempt and returns the number of attempts in the current window. */
    public static function hit(string $key, int $decaySeconds): int
    {
        $db = Database::connection();
        $bucket = app_hash($key);
        $db->execute(
            'INSERT INTO rate_limits (bucket, hits, reset_at)
             VALUES (:b, 1, DATE_ADD(UTC_TIMESTAMP(), INTERVAL :d1 SECOND))
             ON DUPLICATE KEY UPDATE
                hits = IF(reset_at <= UTC_TIMESTAMP(), 1, hits + 1),
                reset_at = IF(reset_at <= UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL :d2 SECOND), reset_at)',
            ['b' => $bucket, 'd1' => $decaySeconds, 'd2' => $decaySeconds]
        );
        if (random_int(1, 100) === 1) {
            $db->execute('DELETE FROM rate_limits WHERE reset_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY) LIMIT 500');
        }
        return (int) $db->fetchValue('SELECT hits FROM rate_limits WHERE bucket = :b', ['b' => $bucket]);
    }

    /** Seconds until the window for $key resets (0 when not limited). */
    public static function availableIn(string $key): int
    {
        $seconds = Database::connection()->fetchValue(
            'SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), reset_at) FROM rate_limits WHERE bucket = :b',
            ['b' => app_hash($key)]
        );
        return max(0, (int) $seconds);
    }

    public static function clear(string $key): void
    {
        Database::connection()->execute('DELETE FROM rate_limits WHERE bucket = :b', ['b' => app_hash($key)]);
    }
}
