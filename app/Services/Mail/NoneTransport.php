<?php

declare(strict_types=1);

namespace App\Services\Mail;

/** Email is not configured: nothing is sent and the outbox row is marked skipped. */
final class NoneTransport implements Transport
{
    public function send(MailMessage $message): MailResult
    {
        return MailResult::skipped('Email is not configured');
    }
}
