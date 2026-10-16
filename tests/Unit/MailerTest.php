<?php

declare(strict_types=1);

use App\Services\Mail\LogTransport;
use App\Services\Mail\MailMessage;
use App\Services\Mail\MailResult;
use App\Services\Mail\Mailer;
use App\Services\Mail\NoneTransport;
use App\Services\Mail\SmtpTransport;
use App\Services\Mail\Transport;

/** A transport that records what it is given. */
final class MailerTestSpy implements Transport
{
    public int $calls = 0;

    public function send(MailMessage $message): MailResult
    {
        $this->calls++;
        return MailResult::accepted();
    }
}

/**
 * Starts a tiny in-process-style SMTP server (separate php process on a random
 * loopback port). Modes: ok | rcpt550 | authfail.
 *
 * @return array{proc: resource, port: int, log: string, dir: string}
 */
function smtp_fixture(string $mode = 'ok'): array
{
    $dir = fx_tmpdir();
    $log = $dir . '/session.log';
    $script = $dir . '/server.php';
    file_put_contents($script, <<<'PHP'
<?php
$mode = $argv[1]; $log = $argv[2];
$srv = stream_socket_server('tcp://127.0.0.1:0', $en, $es);
echo substr(stream_socket_get_name($srv, false), strrpos(stream_socket_get_name($srv, false), ':') + 1) . "\n";
fflush(STDOUT);
$c = @stream_socket_accept($srv, 15);
if (!$c) { exit(1); }
$out = fopen($log, 'w');
$w = function (string $s) use ($c) { fwrite($c, $s); };
$w("220 fake.test ESMTP\r\n");
while (($line = fgets($c)) !== false) {
    fwrite($out, $line); fflush($out);
    $cmd = strtoupper(substr($line, 0, 4));
    if ($cmd === 'EHLO') { $w("250-fake.test\r\n250-SIZE 1000000\r\n250-AUTH LOGIN PLAIN\r\n250 8BITMIME\r\n"); }
    elseif ($cmd === 'AUTH') {
        if (stripos($line, 'AUTH LOGIN') === 0) {
            $w("334 VXNlcm5hbWU6\r\n"); $u = fgets($c); fwrite($out, "[user]\n");
            $w("334 UGFzc3dvcmQ6\r\n"); $p = fgets($c); fwrite($out, "[pass]\n");
            $w($mode === 'authfail' ? "535 5.7.8 Authentication failed\r\n" : "235 2.7.0 Accepted\r\n");
        } else { $w("504 no\r\n"); }
    }
    elseif ($cmd === 'MAIL') { $w("250 OK\r\n"); }
    elseif ($cmd === 'RCPT') { $w($mode === 'rcpt550' ? "550 5.1.1 No such user here\r\n" : "250 OK\r\n"); }
    elseif ($cmd === 'DATA') {
        $w("354 go\r\n"); $data = '';
        while (($l = fgets($c)) !== false) { $data .= $l; if (substr($data, -5) === "\r\n.\r\n") { break; } }
        fwrite($out, "[DATA-BEGIN]\n" . $data . "[DATA-END]\n"); fflush($out);
        $w("250 2.0.0 queued\r\n");
    }
    elseif ($cmd === 'QUIT') { $w("221 bye\r\n"); break; }
    else { $w("500 what\r\n"); }
}
fclose($out);
PHP);
    $php = PHP_BINARY;
    $proc = proc_open([$php, $script, $mode, $log], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) {
        throw new RuntimeException('cannot start fake SMTP server');
    }
    stream_set_timeout($pipes[1], 10);
    $port = (int) trim((string) fgets($pipes[1]));
    if ($port < 1) {
        throw new RuntimeException('fake SMTP server did not start');
    }
    return ['proc' => $proc, 'port' => $port, 'log' => $log, 'dir' => $dir];
}

/** @param array{proc: resource, port: int, log: string, dir: string} $fx */
function smtp_finish(array $fx): string
{
    // Give the server a moment to flush, then stop it.
    for ($i = 0; $i < 20; $i++) {
        $st = proc_get_status($fx['proc']);
        if (!$st['running']) {
            break;
        }
        usleep(50_000);
    }
    proc_terminate($fx['proc']);
    proc_close($fx['proc']);
    $log = is_file($fx['log']) ? (string) file_get_contents($fx['log']) : '';
    fx_rrmdir($fx['dir']);
    return $log;
}

function smtp_transport(int $port, string $user = '', string $pass = '', string $host = '127.0.0.1', string $enc = 'none'): SmtpTransport
{
    return new SmtpTransport($host, $port, $enc, $user, $pass, 'noreply@example.org', 'RIT Foundation Ghana', 'www.example.org', 5);
}

test('mailer: header injection in recipient, subject and reply-to is refused', function (): void {
    $spy = new MailerTestSpy();
    $m = new Mailer($spy);
    foreach ([
        new MailMessage("victim@example.org\r\nBcc: evil@example.org", 'Hi', 'x'),
        new MailMessage("victim@example.org\nBcc: evil@example.org", 'Hi', 'x'),
        new MailMessage('victim@example.org', "Hi\r\nBcc: evil@example.org", 'x'),
        new MailMessage('victim@example.org', "Hi\nBcc: evil@example.org", 'x'),
        new MailMessage('victim@example.org', 'Hi', 'x', "a@example.org\r\nBcc: evil@example.org"),
        new MailMessage('victim@example.org', 'Hi', 'x', 'not an email'),
        new MailMessage('not-an-email', 'Hi', 'x'),
        new MailMessage('', 'Hi', 'x'),
        new MailMessage('a@example.org, b@example.org', 'Hi', 'x'),
    ] as $msg) {
        $r = $m->send($msg);
        expect_same('failed', $r->status, (string) json_encode($msg->to . '|' . $msg->subject));
    }
    expect_same(0, $spy->calls);
    expect_true($m->send(new MailMessage('ok@example.org', 'Fine subject', 'x'))->handedOff());
});

test('mailer: from address is required for mail and smtp', function (): void {
    $m = new Mailer(new MailerTestSpy(), true, '');
    $r = $m->send(new MailMessage('a@example.org', 'Hi', 'x'));
    expect_same('failed', $r->status);
    expect_same('MAIL_FROM_ADDRESS is not set', $r->error);
    expect_same('failed', (new Mailer(new MailerTestSpy(), true, "bad\r\n@x"))->send(new MailMessage('a@example.org', 'Hi', 'x'))->status);
});

test('mailer: none transport skips honestly and fromConfig follows the driver', function (): void {
    $r = (new NoneTransport())->send(new MailMessage('a@example.org', 'Hi', 'x'));
    expect_same('skipped', $r->status);
    expect_same('Email is not configured', $r->error);
    \App\Core\Config::set('mail.driver', 'none');
    expect_same('skipped', Mailer::fromConfig()->send(new MailMessage('a@example.org', 'Hi', 'x'))->status);
    \App\Core\Config::set('mail.driver', 'smtp');
    \App\Core\Config::set('mail.from_address', '');
    $r = Mailer::fromConfig()->send(new MailMessage('a@example.org', 'Hi', 'x'));
    expect_same('MAIL_FROM_ADDRESS is not set', $r->error);
    \App\Core\Config::set('mail.driver', 'none');
});

test('mailer: log transport redacts and is disabled in production', function (): void {
    $dir = fx_tmpdir();
    try {
        $file = $dir . '/mail.log';
        $r = (new LogTransport($file, false))->send(new MailMessage('jane@example.org', 'Subject A', 'SECRET BODY with token=abc'));
        expect_true($r->handedOff());
        $log = (string) file_get_contents($file);
        expect_contains('j***@example.org', $log);
        expect_not_contains('jane@example.org', $log);
        expect_not_contains('SECRET BODY', $log);
        $r = (new LogTransport($dir . '/other.log', true))->send(new MailMessage('jane@example.org', 'S', 'b'));
        expect_same('skipped', $r->status);
        expect_false(is_file($dir . '/other.log'));
    } finally {
        fx_rrmdir($dir);
    }
});

test('smtp: delivers with CRLF, dot-stuffing and encoded headers; AUTH over loopback', function (): void {
    $fx = smtp_fixture('ok');
    $body = "Line one\n.starts with a dot\n..two dots\n\nUnicode: Ama Mensah \u{2013} caf\u{e9} " . str_repeat('long ', 400) . "\n.";
    $r = smtp_transport($fx['port'], 'user@example.org', 's3cretpw')->send(
        new MailMessage('to@example.org', "Caf\u{e9} \u{2013} subject", $body, 'reply@example.org')
    );
    $log = smtp_finish($fx);
    expect_true($r->handedOff(), (string) $r->error);
    expect_contains("EHLO www.example.org\r\n", $log);
    expect_contains("AUTH LOGIN\r\n", $log);
    expect_contains("MAIL FROM:<noreply@example.org>\r\n", $log);
    expect_contains("RCPT TO:<to@example.org>\r\n", $log);
    expect_contains("QUIT\r\n", $log);
    preg_match('/\[DATA-BEGIN\]\n(.*)\[DATA-END\]/s', $log, $m);
    $data = $m[1] ?? '';
    expect_true($data !== '', 'no data captured');
    expect_same(0, preg_match('/(?<!\r)\n/', $data), 'bare LF found');
    foreach (explode("\r\n", $data) as $line) {
        expect_true(strlen($line) <= 998, 'line too long');
    }
    expect_true(str_ends_with($data, "\r\n.\r\n"));
    // Every line that starts with a dot inside the body is doubled; none is a lone terminator before the end.
    $inner = substr($data, 0, -3);
    expect_same(0, preg_match('/\r\n\.(?!\.)/', "\r\n" . $inner), 'unstuffed dot line');
    expect_contains('Subject: =?UTF-8?', $data);
    expect_contains('Reply-To: reply@example.org', $data);
    expect_contains('From: "RIT Foundation Ghana" <noreply@example.org>', $data);
    expect_contains('Content-Type: text/plain; charset=UTF-8', $data);
    expect_contains('MIME-Version: 1.0', $data);
    expect_not_contains('s3cretpw', $log . $data);
});

test('smtp: server-side body round-trips after dot unstuffing', function (): void {
    $fx = smtp_fixture('ok');
    $r = smtp_transport($fx['port'])->send(new MailMessage('to@example.org', 'Dots', "a\n.b\n..c\nd"));
    $log = smtp_finish($fx);
    expect_true($r->handedOff(), (string) $r->error);
    preg_match('/\[DATA-BEGIN\]\n(.*)\[DATA-END\]/s', $log, $m);
    $data = $m[1] ?? '';
    $body = substr($data, (int) strpos($data, "\r\n\r\n") + 4, -3);
    $unstuffed = (string) preg_replace('/^\./m', '', $body);
    $decoded = quoted_printable_decode(str_replace("\r\n", "\n", $unstuffed));
    expect_same("a\n.b\n..c\nd\n", $decoded);
});

test('smtp: credentials are never sent unencrypted to a non-local host', function (): void {
    // Refused before any connection is attempted (TEST-NET address, nothing listens).
    $r = smtp_transport(25, 'user', 'secretpw', '192.0.2.10', 'none')->send(new MailMessage('to@example.org', 'Hi', 'x'));
    expect_same('failed', $r->status);
    expect_contains('without encryption', (string) $r->error);
    expect_not_contains('secretpw', (string) $r->error);
    expect_false($r->connectionFailed);
});

test('smtp: 5xx replies fail honestly without leaking the password', function (): void {
    $fx = smtp_fixture('rcpt550');
    $r = smtp_transport($fx['port'], 'user', 'topsecret99')->send(new MailMessage('to@example.org', 'Hi', 'x'));
    smtp_finish($fx);
    expect_same('failed', $r->status);
    expect_contains('550', (string) $r->error);
    expect_not_contains('topsecret99', (string) $r->error);
    expect_false($r->connectionFailed);

    $fx = smtp_fixture('authfail');
    $r = smtp_transport($fx['port'], 'user', 'topsecret99')->send(new MailMessage('to@example.org', 'Hi', 'x'));
    smtp_finish($fx);
    expect_same('failed', $r->status);
    expect_contains('authentication failed', (string) $r->error);
    expect_not_contains('topsecret99', (string) $r->error);
    expect_not_contains('535', (string) $r->error);
});

test('smtp: an unreachable server is reported as a connection failure', function (): void {
    $srv = stream_socket_server('tcp://127.0.0.1:0');
    $name = (string) stream_socket_get_name($srv, false);
    $port = (int) substr($name, (int) strrpos($name, ':') + 1);
    fclose($srv);
    $r = smtp_transport($port)->send(new MailMessage('to@example.org', 'Hi', 'x'));
    expect_same('failed', $r->status);
    expect_true($r->connectionFailed);
});

test('smtp: starttls is required when asked for and not offered', function (): void {
    $fx = smtp_fixture('ok');
    $r = smtp_transport($fx['port'], '', '', '127.0.0.1', 'tls')->send(new MailMessage('to@example.org', 'Hi', 'x'));
    $log = smtp_finish($fx);
    expect_same('failed', $r->status);
    expect_contains('STARTTLS', (string) $r->error);
    expect_not_contains('MAIL FROM', $log);
});
