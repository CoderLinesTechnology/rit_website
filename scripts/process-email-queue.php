<?php

declare(strict_types=1);

/*
 * Sends queued emails from the outbox. Run from cron every 5-10 minutes:
 *   php /path/to/scripts/process-email-queue.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Mail\Outbox;

$counts = Outbox::process(null, 50);
printf(
    "Email queue processed: %d accepted by the mail server, %d failed, %d skipped.\n",
    $counts['handed_off'],
    $counts['failed'],
    $counts['skipped']
);
