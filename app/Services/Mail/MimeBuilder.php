<?php

declare(strict_types=1);

namespace App\Services\Mail;

/** Builds RFC 5322 / MIME headers and a quoted-printable UTF-8 text body. */
final class MimeBuilder
{
    /**
     * @return array{headers: array<string, string>, body: string}
     */
    public static function build(MailMessage $m, string $fromAddress, string $fromName, string $domain): array
    {
        $headers = [
            'Date' => gmdate('D, d M Y H:i:s') . ' +0000',
            'From' => self::address($fromAddress, $fromName),
            'To' => self::clean($m->to),
        ];
        if ($m->replyTo !== null && $m->replyTo !== '') {
            $headers['Reply-To'] = self::clean($m->replyTo);
        }
        $headers['Subject'] = self::encodeHeader(self::clean($m->subject), 9);
        $headers['Message-ID'] = '<' . bin2hex(random_bytes(12)) . '@' . self::domain($domain) . '>';
        $headers['MIME-Version'] = '1.0';
        $headers['Content-Type'] = 'text/plain; charset=UTF-8';
        $headers['Content-Transfer-Encoding'] = 'quoted-printable';
        $headers['Auto-Submitted'] = 'auto-generated';

        $body = str_replace(["\r\n", "\r"], "\n", $m->textBody);
        $body = str_replace("\n", "\r\n", $body);
        return ['headers' => $headers, 'body' => quoted_printable_encode($body) . "\r\n"];
    }

    public static function address(string $address, string $name): string
    {
        $address = self::clean($address);
        $name = self::clean($name);
        if ($name === '') {
            return $address;
        }
        if (preg_match('/^[\x20-\x7E]*$/', $name) === 1) {
            return '"' . addcslashes($name, '"\\') . '" <' . $address . '>';
        }
        return self::encodeHeader($name, 6) . ' <' . $address . '>';
    }

    public static function encodeHeader(string $value, int $offset): string
    {
        if (preg_match('/^[\x20-\x7E]*$/', $value) === 1 && strlen($value) + $offset <= 76) {
            return $value;
        }
        $encoded = mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n", $offset);
        return $encoded;
    }

    public static function domain(string $domain): string
    {
        $domain = (string) preg_replace('/[^A-Za-z0-9.\-]/', '', $domain);
        return $domain === '' ? 'localhost' : $domain;
    }

    private static function clean(string $value): string
    {
        return trim(str_replace(["\r", "\n", "\0"], '', $value));
    }
}
