<?php

declare(strict_types=1);

namespace App\Services\Mail;

/** Internal to SmtpTransport; the message is already sanitised. */
final class SmtpException extends \RuntimeException
{
    public function __construct(string $message, public readonly bool $connection = false)
    {
        parent::__construct($message);
    }
}
