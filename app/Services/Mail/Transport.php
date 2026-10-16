<?php

declare(strict_types=1);

namespace App\Services\Mail;

interface Transport
{
    /** Must not throw for delivery problems; return a failed result instead. */
    public function send(MailMessage $message): MailResult;
}
