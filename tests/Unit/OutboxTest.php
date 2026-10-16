<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Services\Mail\MailMessage;
use App\Services\Mail\MailResult;
use App\Services\Mail\Mailer;
use App\Services\Mail\Outbox;
use App\Services\Mail\Transport;

/** Returns null when the test database is usable, otherwise the reason to skip. */
function ob_skip_reason(): ?string
{
    static $reason = false;
    if ($reason !== false) {
        return $reason;
    }
    try {
        $name = (string) config('database.name', '');
        if (!str_contains($name, 'test')) {
            return $reason = 'test database not configured (.env.testing DB_NAME must contain "test")';
        }
        Database::connection()->fetchValue('SELECT COUNT(*) FROM email_outbox');
        return $reason = null;
    } catch (Throwable $e) {
        return $reason = 'test database or email_outbox table not available: ' . $e::class;
    }
}

/** Transport returning scripted results and counting sends. */
final class OutboxTestTransport implements Transport
{
    public int $sent = 0;

    /** @param list<MailResult> $results */
    public function __construct(private array $results)
    {
    }

    public function send(MailMessage $message): MailResult
    {
        $this->sent++;
        return array_shift($this->results) ?? MailResult::accepted();
    }
}

/** @return array<string, mixed> */
function ob_row(int $id): array
{
    return Database::connection()->fetch('SELECT * FROM email_outbox WHERE id = :id', ['id' => $id]) ?? [];
}

function ob_cleanup(int $baseline): void
{
    Database::connection()->execute('DELETE FROM email_outbox WHERE id > :b', ['b' => $baseline]);
    Outbox::resetQueued();
}

function ob_baseline(): int
{
    return (int) Database::connection()->fetchValue('SELECT COALESCE(MAX(id), 0) FROM email_outbox');
}

test('outbox: queue inserts a queued row and remembers its id', function () {
    if (($r = ob_skip_reason()) !== null) {
        echo "        ($r)\n";
        return 'skip';
    }
    $b = ob_baseline();
    try {
        Outbox::resetQueued();
        $id = Outbox::queue('a@example.org', "Sub\r\nject", 'Body', 'staff.contact', 'contact_enquiry', 42);
        expect_same([$id], Outbox::queuedThisRequest());
        $row = ob_row($id);
        expect_same('queued', $row['status']);
        expect_same(0, (int) $row['attempts']);
        expect_same('Sub ject', $row['subject']);
        expect_same('Body', $row['body_text']);
        expect_same('contact_enquiry', $row['related_type']);
        expect_same(42, (int) $row['related_id']);
    } finally {
        ob_cleanup($b);
    }
});

test('outbox: handed off rows drop their body (data minimisation)', function () {
    if (($r = ob_skip_reason()) !== null) {
        return 'skip';
    }
    $b = ob_baseline();
    try {
        $id = Outbox::queue('a@example.org', 'S', 'Body with data', 'staff.contact');
        $t = new OutboxTestTransport([MailResult::accepted()]);
        $counts = Outbox::process([$id], 10, new Mailer($t));
        expect_same(['handed_off' => 1, 'failed' => 0, 'skipped' => 0], $counts);
        $row = ob_row($id);
        expect_same('handed_off', $row['status']);
        expect_same(null, $row['body_text']);
        expect_same(1, (int) $row['attempts']);
        expect_true($row['handed_off_at'] !== null && $row['last_attempt_at'] !== null);
        // Processing again must not resend.
        Outbox::process([$id], 10, new Mailer($t));
        expect_same(1, $t->sent);
    } finally {
        ob_cleanup($b);
    }
});

test('outbox: skipped keeps the reason; failures retry up to max_attempts', function () {
    if (($r = ob_skip_reason()) !== null) {
        return 'skip';
    }
    $b = ob_baseline();
    try {
        $skipId = Outbox::queue('a@example.org', 'S', 'Body', 'staff.contact');
        $counts = Outbox::process([$skipId], 10, new Mailer(new \App\Services\Mail\NoneTransport()));
        expect_same(1, $counts['skipped']);
        $row = ob_row($skipId);
        expect_same('skipped', $row['status']);
        expect_same('Email is not configured', $row['last_error']);
        expect_same(null, $row['body_text']);

        Config::set('mail.max_attempts', 2);
        $id = Outbox::queue('b@example.org', 'S', 'Body', 'staff.contact');
        $t = new OutboxTestTransport([MailResult::failed("550 <bob@example.org> nope\r\nsecond line"), MailResult::failed('again'), MailResult::accepted()]);
        $mailer = new Mailer($t);
        expect_same(1, Outbox::process([$id], 10, $mailer)['failed']);
        $row = ob_row($id);
        expect_same('failed', $row['status']);
        expect_same(1, (int) $row['attempts']);
        expect_not_contains("\n", (string) $row['last_error']);
        expect_not_contains('bob@example.org', (string) $row['last_error']);
        expect_contains('b***@example.org', (string) $row['last_error']);
        expect_true($row['body_text'] !== null, 'body must be kept for retries');

        // Not retried while the claim window is still open...
        expect_same(0, Outbox::process([$id], 10, $mailer)['failed']);
        // ...and retried once it has passed.
        Database::connection()->execute('UPDATE email_outbox SET last_attempt_at = UTC_TIMESTAMP() - INTERVAL 10 MINUTE WHERE id = :id', ['id' => $id]);
        expect_same(1, Outbox::process([$id], 10, $mailer)['failed']);
        expect_same(2, (int) ob_row($id)['attempts']);
        // max_attempts reached: no third attempt.
        Database::connection()->execute('UPDATE email_outbox SET last_attempt_at = UTC_TIMESTAMP() - INTERVAL 10 MINUTE WHERE id = :id', ['id' => $id]);
        $counts = Outbox::process([$id], 10, $mailer);
        expect_same(['handed_off' => 0, 'failed' => 0, 'skipped' => 0], $counts);
        expect_same(2, $t->sent);
    } finally {
        Config::set('mail.max_attempts', 3);
        ob_cleanup($b);
    }
});

test('outbox: a connection failure stops the run and leaves the rest queued', function () {
    if (($r = ob_skip_reason()) !== null) {
        return 'skip';
    }
    $b = ob_baseline();
    try {
        $ids = [];
        foreach (['1', '2', '3'] as $n) {
            $ids[] = Outbox::queue("u{$n}@example.org", 'S', 'Body', 'staff.contact');
        }
        $t = new OutboxTestTransport([MailResult::failed('Could not connect', true)]);
        $counts = Outbox::process($ids, 10, new Mailer($t));
        expect_same(1, $t->sent);
        expect_same(1, $counts['failed']);
        expect_same('failed', ob_row($ids[0])['status']);
        expect_same('queued', ob_row($ids[1])['status']);
        expect_same('queued', ob_row($ids[2])['status']);
        expect_same(0, (int) ob_row($ids[1])['attempts']);
    } finally {
        ob_cleanup($b);
    }
});

test('outbox: a claimed row cannot be claimed again (no double send)', function () {
    if (($r = ob_skip_reason()) !== null) {
        return 'skip';
    }
    $b = ob_baseline();
    try {
        $id = Outbox::queue('a@example.org', 'S', 'Body', 'staff.contact');
        // A transport that, while "sending", lets a second worker run over the same row.
        $inner = new OutboxTestTransport([MailResult::accepted()]);
        $second = new OutboxTestTransport([MailResult::accepted()]);
        $mailer = new Mailer(new class ($second, $id, $inner) implements Transport {
            public function __construct(private OutboxTestTransport $second, private int $id, private OutboxTestTransport $inner)
            {
            }

            public function send(MailMessage $message): MailResult
            {
                Outbox::process([$this->id], 10, new Mailer($this->second));
                return $this->inner->send($message);
            }
        });
        Outbox::process([$id], 10, $mailer);
        expect_same(1, $inner->sent);
        expect_same(0, $second->sent, 'second worker sent the same message');
    } finally {
        ob_cleanup($b);
    }
});

test('outbox: process respects ids filter and limit', function () {
    if (($r = ob_skip_reason()) !== null) {
        return 'skip';
    }
    $b = ob_baseline();
    try {
        $ids = [];
        for ($i = 0; $i < 4; $i++) {
            $ids[] = Outbox::queue("u{$i}@example.org", 'S', 'Body', 'staff.contact');
        }
        $t = new OutboxTestTransport([]);
        expect_same(['handed_off' => 0, 'failed' => 0, 'skipped' => 0], Outbox::process([], 10, new Mailer($t)));
        expect_same(2, Outbox::process($ids, 2, new Mailer($t))['handed_off']);
        expect_same('queued', ob_row($ids[2])['status']);
        expect_same(1, Outbox::process([$ids[3]], 10, new Mailer($t))['handed_off']);
        expect_same('queued', ob_row($ids[2])['status']);
    } finally {
        ob_cleanup($b);
    }
});

test('outbox: invalid recipient is marked failed and does not throw', function () {
    if (($r = ob_skip_reason()) !== null) {
        return 'skip';
    }
    $b = ob_baseline();
    try {
        $id = Outbox::queue('not-an-email', 'S', 'Body', 'confirm.contact');
        $counts = Outbox::process([$id], 10, new Mailer(new OutboxTestTransport([])));
        expect_same(1, $counts['failed']);
        expect_same('Invalid recipient address', ob_row($id)['last_error']);
    } finally {
        ob_cleanup($b);
    }
});
