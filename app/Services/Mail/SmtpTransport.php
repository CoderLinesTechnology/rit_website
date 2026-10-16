<?php

declare(strict_types=1);

namespace App\Services\Mail;

/**
 * Minimal SMTP client on native streams: STARTTLS or implicit TLS with strict
 * certificate verification, AUTH LOGIN / PLAIN, dot-stuffed DATA.
 *
 * Errors never include credentials or the AUTH exchange.
 */
final class SmtpTransport implements Transport
{
    private const MAX_REPLY_LINES = 200;

    /** @var resource|null */
    private $socket = null;

    /**
     * @param string $encryption tls (STARTTLS) | ssl (implicit TLS) | none
     */
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $encryption,
        private readonly string $username,
        #[\SensitiveParameter]
        private readonly string $password,
        private readonly string $fromAddress,
        private readonly string $fromName,
        private readonly string $ehloHost = 'localhost',
        private readonly int $timeout = 15,
    ) {
    }

    public function send(MailMessage $message): MailResult
    {
        $encryption = strtolower($this->encryption);
        if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            return MailResult::failed('MAIL_ENCRYPTION must be tls, ssl or none');
        }
        if ($this->host === '' || $this->port < 1 || $this->port > 65535) {
            return MailResult::failed('MAIL_HOST / MAIL_PORT are not set');
        }
        $local = in_array(strtolower($this->host), ['localhost', '127.0.0.1', '::1'], true);
        if ($this->username !== '' && $encryption === 'none' && !$local) {
            return MailResult::failed('Refusing to send the mail password without encryption; use MAIL_ENCRYPTION=tls or ssl');
        }

        try {
            $this->connect($encryption);
            return $this->converse($message, $encryption);
        } catch (SmtpException $e) {
            return MailResult::failed($e->getMessage(), $e->connection);
        } finally {
            $this->close();
        }
    }

    private function connect(string $encryption): void
    {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'peer_name' => $this->host,
                'SNI_enabled' => true,
            ],
        ]);
        $scheme = $encryption === 'ssl' ? 'ssl' : 'tcp';
        $host = str_contains($this->host, ':') ? '[' . $this->host . ']' : $this->host;
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            "{$scheme}://{$host}:{$this->port}",
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if ($socket === false) {
            throw new SmtpException('Could not connect to the mail server (' . self::safe($errstr !== '' ? $errstr : "error {$errno}") . ')', true);
        }
        $this->socket = $socket;
        stream_set_timeout($socket, $this->timeout);
    }

    private function converse(MailMessage $message, string $encryption): MailResult
    {
        $this->expect($this->readReply(), [220], 'greeting', true);
        $caps = $this->ehlo();

        if ($encryption === 'tls') {
            if (!isset($caps['STARTTLS'])) {
                throw new SmtpException('The mail server does not offer STARTTLS; refusing to continue unencrypted');
            }
            $this->expect($this->command('STARTTLS'), [220], 'STARTTLS');
            $method = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $method |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }
            $ok = @stream_socket_enable_crypto($this->sock(), true, $method);
            if ($ok !== true) {
                throw new SmtpException('TLS negotiation with the mail server failed (certificate not trusted or host name mismatch?)');
            }
            $caps = $this->ehlo();
        }

        if ($this->username !== '') {
            $this->authenticate($caps);
        }

        $this->expect($this->command('MAIL FROM:<' . $this->addr($this->fromAddress) . '>'), [250], 'MAIL FROM');
        $this->expect($this->command('RCPT TO:<' . $this->addr($message->to) . '>'), [250, 251], 'RCPT TO');
        $this->expect($this->command('DATA'), [354], 'DATA');

        $domain = MimeBuilder::domain($this->ehloHost);
        $mime = MimeBuilder::build($message, $this->fromAddress, $this->fromName, $domain);
        $raw = '';
        foreach ($mime['headers'] as $name => $value) {
            $raw .= $name . ': ' . $value . "\r\n";
        }
        $raw .= "\r\n" . $mime['body'];
        $this->write(self::dotStuff($raw) . ".\r\n");
        $this->expect($this->readReply(), [250], 'message data');

        // Reply to QUIT is irrelevant: the message has been accepted.
        try {
            $this->command('QUIT');
        } catch (SmtpException) {
        }
        return MailResult::accepted();
    }

    /** @return array<string, string> capability name (upper-case) => parameters */
    private function ehlo(): array
    {
        $host = MimeBuilder::domain($this->ehloHost);
        [$code, $lines] = $this->command('EHLO ' . $host);
        if ($code !== 250) {
            $this->expect($this->command('HELO ' . $host), [250], 'HELO');
            return [];
        }
        $caps = [];
        foreach (array_slice($lines, 1) as $line) {
            $parts = explode(' ', trim($line), 2);
            $caps[strtoupper($parts[0])] = $parts[1] ?? '';
        }
        return $caps;
    }

    /** @param array<string, string> $caps */
    private function authenticate(array $caps): void
    {
        $mechs = isset($caps['AUTH']) ? preg_split('/\s+/', strtoupper($caps['AUTH'])) : [];
        $mechs = is_array($mechs) ? $mechs : [];
        // Some servers advertise "AUTH=LOGIN PLAIN" in addition.
        foreach ($caps as $name => $params) {
            if (str_starts_with($name, 'AUTH=')) {
                $mechs[] = substr($name, 5);
                $mechs = array_merge($mechs, preg_split('/\s+/', strtoupper($params)) ?: []);
            }
        }
        $fail = new SmtpException('SMTP authentication failed (check MAIL_USERNAME / MAIL_PASSWORD)');

        if (in_array('LOGIN', $mechs, true)) {
            [$c] = $this->command('AUTH LOGIN', true);
            if ($c === 334) {
                [$c] = $this->command(base64_encode($this->username), true);
                if ($c !== 334) {
                    throw $fail;
                }
                [$c] = $this->command(base64_encode($this->password), true);
                if ($c === 235) {
                    return;
                }
                throw $fail;
            }
            // LOGIN was not accepted at all: fall back to PLAIN when offered.
            if (!in_array('PLAIN', $mechs, true)) {
                throw $fail;
            }
        }
        if (in_array('PLAIN', $mechs, true)) {
            [$c] = $this->command('AUTH PLAIN ' . base64_encode("\0{$this->username}\0{$this->password}"), true);
            if ($c === 235) {
                return;
            }
            throw $fail;
        }
        throw new SmtpException('The mail server does not offer a supported login method (LOGIN or PLAIN)');
    }

    /**
     * @param list<int> $codes
     * @param array{0: int, 1: list<string>} $reply
     */
    private function expect(array $reply, array $codes, string $stage, bool $connection = false): void
    {
        if (!in_array($reply[0], $codes, true)) {
            throw new SmtpException(
                "The mail server rejected {$stage}: " . self::safe($reply[0] . ' ' . ($reply[1][0] ?? '')),
                $connection
            );
        }
    }

    /**
     * @return array{0: int, 1: list<string>} reply code and text lines
     */
    private function command(string $line, bool $secret = false): array
    {
        if (str_contains($line, "\r") || str_contains($line, "\n")) {
            throw new SmtpException('Invalid SMTP command');
        }
        $this->write($line . "\r\n");
        return $this->readReply($secret);
    }

    /** @return array{0: int, 1: list<string>} */
    private function readReply(bool $secret = false): array
    {
        $lines = [];
        for ($i = 0; $i < self::MAX_REPLY_LINES; $i++) {
            $line = fgets($this->sock(), 2048);
            if ($line === false) {
                $meta = stream_get_meta_data($this->sock());
                throw new SmtpException(
                    ($meta['timed_out'] ?? false) ? 'The mail server timed out' : 'The mail server closed the connection',
                    true
                );
            }
            $line = rtrim($line, "\r\n");
            if (preg_match('/^(\d{3})([ -])?(.*)$/s', $line, $m) !== 1) {
                throw new SmtpException('Unexpected reply from the mail server');
            }
            $lines[] = $secret ? '' : $m[3];
            if (($m[2] ?? ' ') !== '-') {
                return [(int) $m[1], $lines];
            }
        }
        throw new SmtpException('The mail server sent too long a reply');
    }

    private function write(string $data): void
    {
        $sock = $this->sock();
        $length = strlen($data);
        $sent = 0;
        while ($sent < $length) {
            $n = @fwrite($sock, substr($data, $sent));
            if ($n === false || $n === 0) {
                throw new SmtpException('Lost connection while sending to the mail server', true);
            }
            $sent += $n;
        }
    }

    /** Dot-stuffing; input must already use CRLF line endings. */
    public static function dotStuff(string $data): string
    {
        if (!str_ends_with($data, "\r\n")) {
            $data .= "\r\n";
        }
        $data = str_starts_with($data, '.') ? '.' . $data : $data;
        return str_replace("\r\n.", "\r\n..", $data);
    }

    private function addr(string $address): string
    {
        if (preg_match('/[<>\r\n\0 ]/', $address) === 1) {
            throw new SmtpException('Invalid email address');
        }
        return $address;
    }

    /** @return resource */
    private function sock()
    {
        if ($this->socket === null) {
            throw new SmtpException('Not connected', true);
        }
        return $this->socket;
    }

    private function close(): void
    {
        if (is_resource($this->socket)) {
            @fclose($this->socket);
        }
        $this->socket = null;
    }

    private static function safe(string $text): string
    {
        $text = (string) preg_replace('/[^\x20-\x7E]/', '', $text);
        return substr($text, 0, 160);
    }
}
