<?php

declare(strict_types=1);

namespace App\Services\Mail;

/**
 * Outcome of a send attempt.
 *
 * "handed_off" means the SMTP server / local MTA accepted the message. It does
 * NOT mean the message was delivered or read.
 */
final readonly class MailResult
{
    public const HANDED_OFF = 'handed_off';
    public const FAILED = 'failed';
    public const SKIPPED = 'skipped';

    /** @param bool $connectionFailed true when the mail server could not be reached at all */
    public function __construct(
        public string $status,
        public ?string $error = null,
        public bool $connectionFailed = false,
    ) {
    }

    public static function accepted(): self
    {
        return new self(self::HANDED_OFF);
    }

    public static function failed(string $error, bool $connectionFailed = false): self
    {
        return new self(self::FAILED, $error, $connectionFailed);
    }

    public static function skipped(string $reason): self
    {
        return new self(self::SKIPPED, $reason);
    }

    public function handedOff(): bool
    {
        return $this->status === self::HANDED_OFF;
    }
}
