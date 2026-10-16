<?php

declare(strict_types=1);

namespace App\Services\Mail;

/**
 * PHP mail(). A true return only means the local MTA accepted the message for
 * queuing; it says nothing about delivery.
 */
final class NativeMailTransport implements Transport
{
    public function __construct(
        private readonly string $fromAddress,
        private readonly string $fromName,
        private readonly string $domain = 'localhost',
    ) {
    }

    public function send(MailMessage $message): MailResult
    {
        $mime = MimeBuilder::build($message, $this->fromAddress, $this->fromName, $this->domain);
        $headers = $mime['headers'];
        $subject = $headers['Subject'];
        $to = $headers['To'];
        unset($headers['Subject'], $headers['To']);

        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        // Envelope sender only when the address is plain and safe for the sendmail command line.
        $params = preg_match('/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+$/', $this->fromAddress) === 1
            ? '-f' . $this->fromAddress
            : '';

        $ok = @mail($to, $subject, $mime['body'], implode("\r\n", $lines), $params);
        return $ok
            ? MailResult::accepted()
            : MailResult::failed('PHP mail() did not accept the message (check the server mail configuration)');
    }
}
