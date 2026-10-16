<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Core\Str;

/**
 * Development only. Appends a redacted record (masked recipient, subject,
 * body length - never the body) to storage/logs/mail.log.
 */
final class LogTransport implements Transport
{
    public function __construct(private readonly ?string $logFile = null, private readonly bool $production = false)
    {
    }

    public function send(MailMessage $message): MailResult
    {
        if ($this->production) {
            return MailResult::skipped('The "log" mail driver is for development only and is disabled in production');
        }
        $file = $this->logFile ?? (APP_ROOT . '/storage/logs/mail.log');
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            return MailResult::failed('Could not create the mail log folder');
        }
        $line = sprintf(
            "[%s] to=%s subject=%s body_bytes=%d\n",
            gmdate('Y-m-d\TH:i:s\Z'),
            Str::maskEmail($message->to),
            json_encode(str_replace(["\r", "\n"], ' ', $message->subject), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            strlen($message->textBody)
        );
        if (@file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
            return MailResult::failed('Could not write to the mail log');
        }
        return MailResult::accepted();
    }
}
