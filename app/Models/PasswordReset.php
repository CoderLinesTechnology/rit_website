<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Single-use password reset tokens. Only the SHA-256 of the token is stored;
 * the token itself exists only in the emailed link.
 */
final class PasswordReset extends Model
{
    public const TABLE = 'admin_password_resets';
    public const VALID_MINUTES = 60;

    /** Creates a token for the admin (invalidating older ones) and returns it. */
    public static function issue(int $adminId): string
    {
        $token = bin2hex(random_bytes(32));
        static::db()->transaction(static function ($db) use ($adminId, $token): void {
            $db->execute('UPDATE admin_password_resets SET used_at = UTC_TIMESTAMP() WHERE admin_id = :id AND used_at IS NULL', ['id' => $adminId]);
            $db->execute(
                'INSERT INTO admin_password_resets (admin_id, token_hash, expires_at) VALUES (:id, :h, DATE_ADD(UTC_TIMESTAMP(), INTERVAL :m MINUTE))',
                ['id' => $adminId, 'h' => hash('sha256', $token), 'm' => self::VALID_MINUTES]
            );
        });
        return $token;
    }

    /** @return array<string, mixed>|null The unexpired, unused reset row with its active admin. */
    public static function findValid(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        return static::db()->fetch(
            'SELECT r.id, r.admin_id, a.name, a.email FROM admin_password_resets r JOIN admins a ON a.id = r.admin_id
             WHERE r.token_hash = :h AND r.used_at IS NULL AND r.expires_at > UTC_TIMESTAMP() AND a.is_active = 1',
            ['h' => hash('sha256', $token)]
        );
    }

    /** Marks the token used; returns false if it was already consumed (race-safe). */
    public static function consume(int $id): bool
    {
        return static::db()->execute('UPDATE admin_password_resets SET used_at = UTC_TIMESTAMP() WHERE id = :id AND used_at IS NULL', ['id' => $id]) === 1;
    }
}
