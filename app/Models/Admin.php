<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;

/**
 * Administrator accounts. password_hash is never selected except by the
 * authentication code paths that need it.
 */
final class Admin extends Model
{
    public const TABLE = 'admins';
    public const FILLABLE = ['name', 'email', 'password_hash', 'role', 'is_active', 'password_changed_at'];
    private const PUBLIC_COLUMNS = 'id, name, email, role, is_active, last_login_at, password_changed_at, created_at, updated_at';

    /** @return array<string, mixed>|null (without the password hash) */
    public static function find(int $id): ?array
    {
        return static::db()->fetch('SELECT ' . self::PUBLIC_COLUMNS . ' FROM admins WHERE id = :id', ['id' => $id]);
    }

    /** @return array<string, mixed>|null (without the password hash) */
    public static function findByEmail(string $email): ?array
    {
        return static::db()->fetch('SELECT ' . self::PUBLIC_COLUMNS . ' FROM admins WHERE email = :e', ['e' => strtolower(trim($email))]);
    }

    public static function passwordHash(int $id): ?string
    {
        $hash = static::db()->fetchValue('SELECT password_hash FROM admins WHERE id = :id', ['id' => $id]);
        return is_string($hash) ? $hash : null;
    }

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return static::db()->fetchAll('SELECT ' . self::PUBLIC_COLUMNS . ' FROM admins ORDER BY is_active DESC, name LIMIT 500');
    }

    public static function count(): int
    {
        return (int) static::db()->fetchValue('SELECT COUNT(*) FROM admins');
    }

    /**
     * Active super administrators other than $exceptId. Used to refuse any
     * change that would leave nobody able to manage accounts. Call inside a
     * transaction with FOR UPDATE semantics via lockSuperAdmins().
     */
    public static function otherActiveSuperAdmins(int $exceptId): int
    {
        return (int) static::db()->fetchValue(
            "SELECT COUNT(*) FROM admins WHERE role = 'super_admin' AND is_active = 1 AND id <> :id",
            ['id' => $exceptId]
        );
    }

    /** Locks the super-admin rows for the rest of the current transaction. */
    public static function lockSuperAdmins(): void
    {
        static::db()->fetchAll("SELECT id FROM admins WHERE role = 'super_admin' FOR UPDATE");
    }

    public static function setPassword(int $id, string $password): void
    {
        static::db()->execute(
            'UPDATE admins SET password_hash = :h, password_changed_at = UTC_TIMESTAMP(), session_version = session_version + 1 WHERE id = :id',
            ['h' => Auth::hashPassword($password), 'id' => $id]
        );
        static::db()->execute('UPDATE admin_password_resets SET used_at = UTC_TIMESTAMP() WHERE admin_id = :id AND used_at IS NULL', ['id' => $id]);
    }

    /** Ends every session of this administrator (role change, deactivation). */
    public static function revokeSessions(int $id): void
    {
        static::db()->execute('UPDATE admins SET session_version = session_version + 1 WHERE id = :id', ['id' => $id]);
    }

    public static function emailTaken(string $email, ?int $exceptId = null): bool
    {
        return static::db()->fetchValue(
            'SELECT 1 FROM admins WHERE email = :e AND id <> :id',
            ['e' => strtolower(trim($email)), 'id' => $exceptId ?? 0]
        ) !== null;
    }
}
