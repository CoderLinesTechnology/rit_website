<?php

declare(strict_types=1);

/*
 * Sends one test message straight through the configured mail driver:
 *   php scripts/test-mail.php recipient@example.org
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Mail\MailMessage;
use App\Services\Mail\MailResult;
use App\Services\Mail\Mailer;

$to = $argv[1] ?? '';
if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "Usage: php scripts/test-mail.php recipient@example.org\n");
    exit(2);
}

$driver = (string) config('mail.driver', 'none');
echo "Mail driver: {$driver}\n";

$result = Mailer::fromConfig()->send(new MailMessage(
    $to,
    '[RIT Foundation Ghana] Test email',
    "This is a test message from the RIT Foundation Ghana website.\n\nIf you can read it, outgoing email is working.\n"
));

switch ($result->status) {
    case MailResult::HANDED_OFF:
        echo "Accepted by the mail server (this does not prove delivery; check the inbox and spam folder).\n";
        exit(0);
    case MailResult::SKIPPED:
        echo 'Not sent: ' . ($result->error ?? 'email is not configured') . "\n";
        exit(1);
    default:
        echo 'Failed: ' . ($result->error ?? 'unknown error') . "\n";
        exit(1);
}
