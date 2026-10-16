<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Request;

/**
 * Shared behaviour of the four private submission tables (training
 * applications, volunteer applications, partnership and contact enquiries).
 * These rows hold personal data: only routes guarded by
 * "can:submissions.manage" may read them.
 */
abstract class Submission extends Model
{
    /** @var list<string> */
    public const STATUSES = [];

    /** Prefix of the public reference number, e.g. RIT-APP. */
    public const REFERENCE_PREFIX = '';

    /** Columns searched by the admin "q" filter (besides reference). */
    public const SEARCH_COLUMNS = ['full_name', 'email'];

    /** @return array<string, mixed>|null */
    public static function findByIdempotencyKey(string $key): ?array
    {
        return static::db()->fetch('SELECT id, reference FROM `' . static::TABLE . '` WHERE idempotency_key = :k', ['k' => $key]);
    }

    /** A reference number that is not yet used in this table. */
    public static function newReference(): string
    {
        do {
            $reference = \App\Core\Str::reference(static::REFERENCE_PREFIX);
        } while (static::db()->fetchValue('SELECT 1 FROM `' . static::TABLE . '` WHERE reference = :r', ['r' => $reference]) !== null);
        return $reference;
    }

    /**
     * Admin listing with filters: status, q (reference/name/email), from, to (Y-m-d).
     *
     * @param array{where?: string, params?: array<string, mixed>} $extra Additional filter from a subclass.
     * @return array{items: list<array<string, mixed>>, paginator: \App\Core\Paginator}
     */
    public static function adminPaginate(Request $request, int $perPage = 25, string $select = 's.*', string $from = '', array $extra = []): array
    {
        $from = $from !== '' ? $from : '`' . static::TABLE . '` s';
        $where = '1 = 1';
        $params = [];
        $status = $request->query('status');
        if (in_array($status, static::STATUSES, true)) {
            $where .= ' AND s.status = :status';
            $params['status'] = $status;
        }
        $q = mb_substr($request->query('q'), 0, 100);
        if ($q !== '') {
            $clauses = ['s.reference = :qref'];
            $params['qref'] = strtoupper($q);
            foreach (static::SEARCH_COLUMNS as $i => $column) {
                $clauses[] = "s.`{$column}` LIKE :q{$i}";
                $params['q' . $i] = Database::likeTerm($q);
            }
            $where .= ' AND (' . implode(' OR ', $clauses) . ')';
        }
        foreach (['from' => '>=', 'to' => '<'] as $key => $op) {
            $date = $request->query($key);
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if ($parsed !== false && $parsed->format('Y-m-d') === $date) {
                $where .= " AND s.created_at {$op} :{$key}";
                $params[$key] = $key === 'to' ? $parsed->modify('+1 day')->format('Y-m-d') : $date;
            }
        }
        if (($extra['where'] ?? '') !== '') {
            $where .= ' AND ' . $extra['where'];
            $params += $extra['params'] ?? [];
        }
        return static::paginateQuery($request, $select, $from, $where, $params, 's.created_at DESC, s.id DESC', $perPage);
    }

    public static function updateStatus(int $id, string $status, ?int $adminId): bool
    {
        if (!in_array($status, static::STATUSES, true)) {
            throw new \InvalidArgumentException('Invalid status.');
        }
        return static::db()->execute(
            'UPDATE `' . static::TABLE . '` SET status = :s, status_changed_at = UTC_TIMESTAMP(), status_changed_by = :a WHERE id = :id AND status <> :s2',
            ['s' => $status, 'a' => $adminId, 'id' => $id, 's2' => $status]
        ) > 0;
    }

    public static function updateNotes(int $id, ?string $notes): void
    {
        static::db()->execute('UPDATE `' . static::TABLE . '` SET admin_notes = :n WHERE id = :id', ['n' => $notes, 'id' => $id]);
    }

    /** @return array<string, int> status => count (every status present, zero-filled). */
    public static function countByStatus(): array
    {
        $counts = array_fill_keys(static::STATUSES, 0);
        foreach (static::db()->fetchAll('SELECT status, COUNT(*) AS n FROM `' . static::TABLE . '` GROUP BY status') as $row) {
            $counts[(string) $row['status']] = (int) $row['n'];
        }
        return $counts;
    }

    public static function countNew(): int
    {
        return (int) static::db()->fetchValue("SELECT COUNT(*) FROM `" . static::TABLE . "` WHERE status = 'new'");
    }

    /** @return list<array<string, mixed>> */
    public static function recent(int $limit = 5): array
    {
        return static::db()->fetchAll('SELECT * FROM `' . static::TABLE . '` ORDER BY created_at DESC, id DESC LIMIT :lim', ['lim' => $limit]);
    }

    /** @return array<int, int> month (1–12) => submissions received in that month of $year. */
    public static function monthlyCounts(int $year): array
    {
        $counts = array_fill(1, 12, 0);
        $rows = static::db()->fetchAll(
            'SELECT MONTH(created_at) AS m, COUNT(*) AS n FROM `' . static::TABLE . '`
             WHERE created_at >= :start AND created_at < :end GROUP BY MONTH(created_at)',
            ['start' => sprintf('%04d-01-01', $year), 'end' => sprintf('%04d-01-01', $year + 1)]
        );
        foreach ($rows as $row) {
            $counts[(int) $row['m']] = (int) $row['n'];
        }
        return $counts;
    }

    public static function countSince(string $utcDateTime): int
    {
        return (int) static::db()->fetchValue('SELECT COUNT(*) FROM `' . static::TABLE . '` WHERE created_at >= :d', ['d' => $utcDateTime]);
    }

    /** @return list<array<string, mixed>> Rows older than the retention period (for the purge script). */
    public static function olderThanMonths(int $months, int $limit = 500): array
    {
        return static::db()->fetchAll(
            'SELECT * FROM `' . static::TABLE . '` WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL :m MONTH) ORDER BY id LIMIT :lim',
            ['m' => $months, 'lim' => $limit]
        );
    }
}
