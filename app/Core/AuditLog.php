<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Records sensitive administrative actions (sign-ins, status changes,
 * deletions, settings and account changes). Summaries must describe the
 * action without copying applicants' personal data into the log.
 */
final class AuditLog
{
    public static function record(string $action, ?string $entityType = null, ?int $entityId = null, string $summary = '', ?int $adminId = null): void
    {
        try {
            $adminId ??= Auth::id();
            Database::connection()->insert('admin_audit_logs', [
                'admin_id' => $adminId,
                'action' => mb_substr($action, 0, 80),
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'summary' => mb_substr(str_replace(["\r", "\n"], ' ', $summary), 0, 500),
                'ip_address' => self::ip(),
                'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
            ]);
        } catch (\Throwable $e) {
            // An audit failure must never break the action itself, but it must be visible.
            Logger::error('Audit log write failed: ' . $e->getMessage(), ['action' => $action]);
        }
    }

    private static function ip(): ?string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }
}
