<?php

declare(strict_types=1);

/*
 * Data retention: deletes private submissions (and their private attachments),
 * old audit entries and old email-outbox rows after the configured periods
 * (RETENTION_SUBMISSION_MONTHS / RETENTION_AUDIT_MONTHS in .env).
 *
 *   php scripts/purge-old-records.php --dry-run   (report only)
 *   php scripts/purge-old-records.php             (delete)
 *
 * Suggested cron: monthly.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\AuditLog;
use App\Core\Database;
use App\Models\ContactEnquiry;
use App\Models\PartnershipEnquiry;
use App\Models\TrainingApplication;
use App\Models\VolunteerApplication;
use App\Services\DocumentStore;

$dryRun = in_array('--dry-run', $argv, true);
$months = max(1, (int) config('app.retention.submissions_months', 24));
$auditMonths = max(1, (int) config('app.retention.audit_months', 24));
$db = Database::connection();
$documents = new DocumentStore();
$summary = [];

// model => email_outbox.related_type used by App\Services\Notifier
$models = [
    TrainingApplication::class => 'training_application',
    VolunteerApplication::class => 'volunteer_application',
    PartnershipEnquiry::class => 'partnership_enquiry',
    ContactEnquiry::class => 'contact_enquiry',
];
foreach ($models as $model => $relatedType) {
    $count = 0;
    do {
        $rows = $model::olderThanMonths($months, 200);
        foreach ($rows as $row) {
            $count++;
            if ($dryRun) {
                continue;
            }
            if (!empty($row['attachment_path'])) {
                $documents->delete((string) $row['attachment_path']);
            }
            $db->execute('DELETE FROM email_outbox WHERE related_type = :t AND related_id = :id', [
                't' => $relatedType,
                'id' => (int) $row['id'],
            ]);
            $model::delete((int) $row['id']);
        }
    } while (!$dryRun && count($rows) === 200);
    $summary[$model::TABLE] = $count;
}

// Donations that never completed (no money taken) are kept only for the retention period.
// Successful donations are financial records: their retention is a decision for the Foundation.
$donationCount = (int) $db->fetchValue(
    "SELECT COUNT(*) FROM donations WHERE status IN ('initiated','pending','abandoned','failed') AND created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL :m MONTH)",
    ['m' => $months]
);
if (!$dryRun) {
    $db->execute(
        "DELETE FROM donations WHERE status IN ('initiated','pending','abandoned','failed') AND created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL :m MONTH)",
        ['m' => $months]
    );
}
$summary['donations (not completed)'] = $donationCount;

// Application log files older than 90 days.
$logCount = 0;
foreach (glob(APP_ROOT . '/storage/logs/app-*.log') ?: [] as $logFile) {
    if (filemtime($logFile) < time() - 90 * 86400) {
        $logCount++;
        if (!$dryRun) {
            @unlink($logFile);
        }
    }
}
$summary['log files (> 90 days)'] = $logCount;

$auditCount = (int) $db->fetchValue('SELECT COUNT(*) FROM admin_audit_logs WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL :m MONTH)', ['m' => $auditMonths]);
$outboxCount = (int) $db->fetchValue("SELECT COUNT(*) FROM email_outbox WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 6 MONTH)");
if (!$dryRun) {
    $db->execute('DELETE FROM admin_audit_logs WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL :m MONTH)', ['m' => $auditMonths]);
    $db->execute('DELETE FROM email_outbox WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 6 MONTH)');
    $db->execute('DELETE FROM admin_password_resets WHERE expires_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)');
    AuditLog::record('retention.purge', null, null, 'Retention purge: ' . json_encode($summary) . ", audit {$auditCount}, outbox {$outboxCount}", null);
}
$summary['admin_audit_logs'] = $auditCount;
$summary['email_outbox'] = $outboxCount;

echo ($dryRun ? "[dry run] Would delete:\n" : "Deleted:\n");
foreach ($summary as $table => $count) {
    echo sprintf("  %-26s %d\n", $table, $count);
}
