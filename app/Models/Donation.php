<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Request;

/**
 * Donation records. Card numbers, CVV, PINs or wallet credentials are never
 * stored — only our reference, the provider's reference, amount and status.
 */
final class Donation extends Model
{
    public const TABLE = 'donations';
    public const REFERENCE_PREFIX = 'RIT-DON';
    public const STATUSES = ['initiated', 'pending', 'successful', 'failed', 'abandoned', 'refunded'];
    public const FILLABLE = [
        'reference', 'provider', 'provider_reference', 'amount_minor', 'currency', 'donor_name', 'donor_email',
        'is_anonymous', 'status', 'provider_status', 'verified_at',
    ];

    /** @return array<string, mixed>|null */
    public static function findByReference(string $reference): ?array
    {
        return static::db()->fetch('SELECT * FROM donations WHERE reference = :r', ['r' => $reference]);
    }

    public static function newReference(): string
    {
        do {
            $reference = \App\Core\Str::reference(self::REFERENCE_PREFIX);
        } while (static::findByReference($reference) !== null);
        return $reference;
    }

    /**
     * Status changes only move forward from a non-final state, so a late or
     * replayed webhook can never turn a verified success back into "pending".
     */
    public static function applyVerifiedStatus(int $id, string $status, ?string $providerReference, ?string $providerStatus): bool
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException('Invalid donation status.');
        }
        return static::db()->execute(
            "UPDATE donations SET status = :s, provider_reference = COALESCE(:pr, provider_reference), provider_status = :ps,
                    verified_at = UTC_TIMESTAMP()
             WHERE id = :id AND (status IN ('initiated', 'pending') OR (status = 'successful' AND :s2 = 'refunded'))",
            ['s' => $status, 'pr' => $providerReference, 'ps' => $providerStatus, 'id' => $id, 's2' => $status]
        ) > 0;
    }

    /** @return array{items: list<array<string, mixed>>, paginator: \App\Core\Paginator} */
    public static function adminPaginate(Request $request, int $perPage = 25): array
    {
        $where = '1 = 1';
        $params = [];
        $status = $request->query('status');
        if (in_array($status, self::STATUSES, true)) {
            $where .= ' AND d.status = :status';
            $params['status'] = $status;
        }
        return static::paginateQuery($request, 'd.*', 'donations d', $where, $params, 'd.created_at DESC, d.id DESC', $perPage);
    }

    /** @return array{count: int, total_minor: int} Verified successful donations. */
    public static function successfulTotals(): array
    {
        $row = static::db()->fetch("SELECT COUNT(*) AS c, COALESCE(SUM(amount_minor), 0) AS t FROM donations WHERE status = 'successful' AND verified_at IS NOT NULL");
        return ['count' => (int) ($row['c'] ?? 0), 'total_minor' => (int) ($row['t'] ?? 0)];
    }

    /** @return list<array<string, mixed>> */
    public static function events(int $donationId): array
    {
        return static::db()->fetchAll(
            'SELECT id, provider, event_type, signature_valid, processed_at, received_at FROM payment_events WHERE donation_id = :id ORDER BY id',
            ['id' => $donationId]
        );
    }

    /**
     * Records a webhook event once; returns false when the provider's event id
     * was already received (replay / retry).
     */
    public static function recordEvent(?int $donationId, string $provider, string $eventId, string $eventType, bool $signatureValid, string $rawPayload): bool
    {
        try {
            static::db()->insert('payment_events', [
                'donation_id' => $donationId,
                'provider' => $provider,
                'event_id' => mb_substr($eventId, 0, 190),
                'event_type' => mb_substr($eventType, 0, 100),
                'signature_valid' => $signatureValid,
                'payload_sha256' => hash('sha256', $rawPayload),
            ]);
            return true;
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                return false;
            }
            throw $e;
        }
    }

    public static function eventProcessed(string $provider, string $eventId): bool
    {
        return static::db()->fetchValue(
            'SELECT processed_at FROM payment_events WHERE provider = :p AND event_id = :e',
            ['p' => $provider, 'e' => mb_substr($eventId, 0, 190)]
        ) !== null;
    }

    public static function markEventProcessed(string $provider, string $eventId): void
    {
        static::db()->execute(
            'UPDATE payment_events SET processed_at = UTC_TIMESTAMP() WHERE provider = :p AND event_id = :e',
            ['p' => $provider, 'e' => $eventId]
        );
    }
}
