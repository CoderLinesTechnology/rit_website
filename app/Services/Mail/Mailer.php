<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Core\Logger;

/** Validates a message and hands it to the configured transport. Never throws for send problems. */
final class Mailer
{
    public function __construct(
        private readonly Transport $transport,
        private readonly bool $requiresFromAddress = false,
        private readonly string $fromAddress = '',
    ) {
    }

    public static function fromConfig(): self
    {
        $driver = (string) config('mail.driver', 'none');
        $from = trim((string) config('mail.from_address', ''));
        $fromName = (string) config('mail.from_name', 'RIT Foundation Ghana');
        $host = parse_url((string) config('app.url', ''), PHP_URL_HOST);
        $domain = is_string($host) && $host !== '' ? $host : 'localhost';

        return match ($driver) {
            'smtp' => new self(new SmtpTransport(
                host: trim((string) config('mail.host', '')),
                port: (int) config('mail.port', 587),
                encryption: (string) config('mail.encryption', 'tls'),
                username: (string) config('mail.username', ''),
                password: (string) config('mail.password', ''),
                fromAddress: $from,
                fromName: $fromName,
                ehloHost: $domain,
                timeout: max(1, (int) config('mail.timeout', 15)),
            ), true, $from),
            'mail' => new self(new NativeMailTransport($from, $fromName, $domain), true, $from),
            'log' => new self(new LogTransport(null, config('app.env') === 'production')),
            default => new self(new NoneTransport()),
        };
    }

    public function send(MailMessage $message): MailResult
    {
        $to = trim($message->to);
        if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false || self::hasLineBreak($to)) {
            return MailResult::failed('Invalid recipient address');
        }
        if (self::hasLineBreak($message->subject) || ($message->replyTo !== null && self::hasLineBreak($message->replyTo))) {
            return MailResult::failed('Invalid header value (line breaks are not allowed)');
        }
        if ($message->replyTo !== null && $message->replyTo !== ''
            && filter_var($message->replyTo, FILTER_VALIDATE_EMAIL) === false) {
            return MailResult::failed('Invalid reply-to address');
        }
        if ($this->requiresFromAddress) {
            if ($this->fromAddress === '') {
                return MailResult::failed('MAIL_FROM_ADDRESS is not set');
            }
            if (filter_var($this->fromAddress, FILTER_VALIDATE_EMAIL) === false || self::hasLineBreak($this->fromAddress)) {
                return MailResult::failed('MAIL_FROM_ADDRESS is not a valid email address');
            }
        }

        $clean = new MailMessage($to, $message->subject, $message->textBody, $message->replyTo);
        try {
            return $this->transport->send($clean);
        } catch (\Throwable $e) {
            Logger::warning('Mail transport threw', ['class' => $e::class]);
            return MailResult::failed('The mail transport failed unexpectedly');
        }
    }

    private static function hasLineBreak(string $value): bool
    {
        return preg_match('/[\r\n\0]/', $value) === 1;
    }
}
