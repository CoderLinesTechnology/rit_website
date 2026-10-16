<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Request;

/** Read-only listings of the audit log and the email outbox for administrators. */
final class LogEntry extends Model
{
    /** @return array{items: list<array<string, mixed>>, paginator: \App\Core\Paginator} */
    public static function auditPaginate(Request $request, int $perPage = 50): array
    {
        $where = '1 = 1';
        $params = [];
        $action = $request->query('action');
        if ($action !== '' && preg_match('/^[a-z_.]{1,80}$/', $action)) {
            $where .= ' AND l.action LIKE :action';
            $params['action'] = $action . '%';
        }
        $admin = $request->queryInt('admin');
        if ($admin > 0) {
            $where .= ' AND l.admin_id = :admin';
            $params['admin'] = $admin;
        }
        return static::paginateQuery(
            $request,
            'l.id, l.action, l.entity_type, l.entity_id, l.summary, l.ip_address, l.created_at, a.name AS admin_name',
            'admin_audit_logs l LEFT JOIN admins a ON a.id = l.admin_id',
            $where,
            $params,
            'l.id DESC',
            $perPage
        );
    }

    /**
     * Latest audit entries for the dashboard feed, limited to what the viewer may see:
     * submission activity only with submissions access, account activity only for account managers.
     *
     * @return list<array<string, mixed>>
     */
    public static function recentAudit(int $limit = 6, bool $includeSubmissions = false, bool $includeAccounts = false): array
    {
        $where = "l.action NOT LIKE 'auth.%'";
        if (!$includeSubmissions) {
            $where .= " AND l.action NOT LIKE 'application.%' AND l.action NOT LIKE 'submission.%'"
                . " AND l.action NOT LIKE 'volunteer.%' AND l.action NOT LIKE 'partnership.%' AND l.action NOT LIKE 'contact.%'";
        }
        if (!$includeAccounts) {
            $where .= " AND l.action NOT LIKE 'admin.%' AND l.action NOT LIKE 'account.%'";
        }
        return static::db()->fetchAll(
            "SELECT l.action, l.entity_type, l.entity_id, l.summary, l.created_at, a.name AS admin_name
             FROM admin_audit_logs l LEFT JOIN admins a ON a.id = l.admin_id
             WHERE {$where}
             ORDER BY l.id DESC LIMIT :lim",
            ['lim' => $limit]
        );
    }

    /**
     * Outbox rows without message bodies; recipients are masked for display.
     *
     * @return array{items: list<array<string, mixed>>, paginator: \App\Core\Paginator}
     */
    public static function emailPaginate(Request $request, int $perPage = 50): array
    {
        $where = '1 = 1';
        $params = [];
        $status = $request->query('status');
        if (in_array($status, ['queued', 'handed_off', 'failed', 'skipped'], true)) {
            $where .= ' AND o.status = :status';
            $params['status'] = $status;
        }
        return static::paginateQuery(
            $request,
            'o.id, o.purpose, o.related_type, o.related_id, o.recipient, o.subject, o.status, o.attempts, o.last_error, o.last_attempt_at, o.handed_off_at, o.created_at',
            'email_outbox o',
            $where,
            $params,
            'o.id DESC',
            $perPage
        );
    }

    /** @return array<string, int> outbox status => count */
    public static function emailStatusCounts(): array
    {
        $counts = ['queued' => 0, 'handed_off' => 0, 'failed' => 0, 'skipped' => 0];
        foreach (static::db()->fetchAll('SELECT status, COUNT(*) AS n FROM email_outbox GROUP BY status') as $row) {
            $counts[(string) $row['status']] = (int) $row['n'];
        }
        return $counts;
    }
}
