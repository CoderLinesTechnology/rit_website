<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Str;

/**
 * Transactional outbox. Callers queue a row inside the same DB transaction as
 * the submission; sending happens afterwards (end of request or cron) so a
 * mail outage never loses or blocks a submission.
 */
final class Outbox
{
    /** A claimed row is not picked up again by another worker for this many minutes. */
    private const CLAIM_MINUTES = 3;

    /** @var list<int> ids queued during this request (documented static state) */
    private static array $queued = [];

    public static function queue(
        string $to,
        string $subject,
        string $body,
        string $purpose,
        ?string $relatedType = null,
        ?int $relatedId = null,
    ): int {
        $id = Database::connection()->insert('email_outbox', [
            'purpose' => $purpose,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'recipient' => mb_substr(trim($to), 0, 190),
            'subject' => mb_substr(self::oneLine($subject), 0, 255),
            'body_text' => $body,
            'status' => 'queued',
        ]);
        self::$queued[] = $id;
        return $id;
    }

    /** Records a notification that cannot be sent (visible to administrators). */
    public static function recordSkipped(
        string $to,
        string $subject,
        string $purpose,
        string $reason,
        ?string $relatedType = null,
        ?int $relatedId = null,
    ): int {
        return Database::connection()->insert('email_outbox', [
            'purpose' => $purpose,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'recipient' => mb_substr($to, 0, 190),
            'subject' => mb_substr(self::oneLine($subject), 0, 255),
            'body_text' => null,
            'status' => 'skipped',
            'last_error' => mb_substr($reason, 0, 500),
        ]);
    }

    /** @return list<int> */
    public static function queuedThisRequest(): array
    {
        return self::$queued;
    }

    /** For tests. */
    public static function resetQueued(): void
    {
        self::$queued = [];
    }

    /**
     * @param list<int>|null $ids restrict to these rows (e.g. the ones queued by this request)
     * @return array{handed_off: int, failed: int, skipped: int}
     */
    public static function process(?array $ids = null, int $limit = 10, ?Mailer $mailer = null): array
    {
        $counts = ['handed_off' => 0, 'failed' => 0, 'skipped' => 0];
        if ($ids !== null && $ids === []) {
            return $counts;
        }
        $db = Database::connection();
        $mailer ??= Mailer::fromConfig();
        $maxAttempts = max(1, (int) config('mail.max_attempts', 3));

        $params = ['max' => $maxAttempts, 'limit' => max(1, $limit)];
        $idSql = '';
        if ($ids !== null) {
            $holders = [];
            foreach (array_values($ids) as $i => $id) {
                $holders[] = ':id' . $i;
                $params['id' . $i] = (int) $id;
            }
            $idSql = ' AND id IN (' . implode(',', $holders) . ')';
        }
        $rows = $db->fetchAll(
            "SELECT id, recipient, subject, body_text, attempts FROM email_outbox
             WHERE (status = 'queued' OR (status = 'failed' AND attempts < :max))
               AND (last_attempt_at IS NULL OR last_attempt_at < UTC_TIMESTAMP() - INTERVAL " . self::CLAIM_MINUTES . " MINUTE)"
            . $idSql . ' ORDER BY id LIMIT :limit',
            $params
        );

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $claimed = $db->execute(
                "UPDATE email_outbox SET attempts = attempts + 1, last_attempt_at = UTC_TIMESTAMP()
                 WHERE id = :id AND status IN ('queued','failed') AND attempts = :seen
                   AND (last_attempt_at IS NULL OR last_attempt_at < UTC_TIMESTAMP() - INTERVAL " . self::CLAIM_MINUTES . " MINUTE)",
                ['id' => $id, 'seen' => (int) $row['attempts']]
            );
            if ($claimed !== 1) {
                continue; // another worker has it
            }

            $body = $row['body_text'];
            if (!is_string($body) || $body === '') {
                $result = MailResult::failed('Message body is missing');
            } else {
                $result = $mailer->send(new MailMessage((string) $row['recipient'], (string) $row['subject'], $body));
            }

            if ($result->handedOff()) {
                $db->execute(
                    "UPDATE email_outbox SET status = 'handed_off', handed_off_at = UTC_TIMESTAMP(), body_text = NULL, last_error = NULL WHERE id = :id",
                    ['id' => $id]
                );
                $counts['handed_off']++;
            } elseif ($result->status === MailResult::SKIPPED) {
                $db->execute(
                    "UPDATE email_outbox SET status = 'skipped', body_text = NULL, last_error = :err WHERE id = :id",
                    ['id' => $id, 'err' => self::sanitise($result->error ?? 'Skipped')]
                );
                $counts['skipped']++;
            } else {
                $error = self::sanitise($result->error ?? 'Send failed');
                // After the last attempt the body is no longer needed: erase it, so personal data
                // and single-use links (e.g. password resets) do not linger in failed rows.
                $db->execute(
                    "UPDATE email_outbox SET status = 'failed', last_error = :err,
                            body_text = IF(attempts >= :max, NULL, body_text) WHERE id = :id",
                    ['id' => $id, 'err' => $error, 'max' => $maxAttempts]
                );
                $counts['failed']++;
                Logger::warning('Email send failed', ['outbox_id' => $id, 'error' => $error]);
                if ($result->connectionFailed) {
                    break; // the server is unreachable: leave the rest queued instead of waiting on each
                }
            }
        }
        return $counts;
    }

    private static function oneLine(string $text): string
    {
        return trim((string) preg_replace('/\s*[\r\n]+\s*/', ' ', $text));
    }

    private static function sanitise(string $text): string
    {
        $text = (string) preg_replace('/[^\P{C}]+/u', ' ', $text);
        // Servers often echo the rejected address back; keep recipients out of logs and the admin UI.
        $text = (string) preg_replace_callback(
            '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+/',
            static fn (array $m): string => Str::maskEmail($m[0]),
            $text
        );
        return mb_substr(trim($text), 0, 500);
    }
}
