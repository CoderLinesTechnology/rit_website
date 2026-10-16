<?php

declare(strict_types=1);

namespace App\Services\Mail;

/** A plain-text email. Validation happens in Mailer::send(). */
final readonly class MailMessage
{
    public function __construct(
        public string $to,
        public string $subject,
        public string $textBody,
        public ?string $replyTo = null,
    ) {
    }
}
