<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Services\Mail\Outbox;
use App\Services\Notifier;

/**
 * @param array<string, string> $settings
 * @return list<array<string, mixed>> rows created since $baseline
 */
function nt_rows(int $baseline): array
{
    return Database::connection()->fetchAll('SELECT * FROM email_outbox WHERE id > :b ORDER BY id', ['b' => $baseline]);
}

/** @param array<string, string> $settings */
function nt_notifier(array $settings): Notifier
{
    return new Notifier(static fn (string $key, string $default): string => $settings[$key] ?? $default);
}

test('notifier: training application queues staff notice and confirmation', function () {
    if (($r = ob_skip_reason()) !== null) {
        echo "        ($r)\n";
        return 'skip';
    }
    $b = ob_baseline();
    $old = config('app.url');
    try {
        Config::set('app.url', 'https://www.example.org');
        nt_notifier(['notify.email' => 'staff@example.org'])
            ->trainingApplicationReceived(17, "RIT-APP-7K3QX9MD", "Digital Skills\r\nBcc: x@evil.test", 'Ama Mensah', 'ama@example.org');
        $rows = nt_rows($b);
        expect_same(2, count($rows));
        [$staff, $confirm] = $rows;

        expect_same('staff.training_application', $staff['purpose']);
        expect_same('training_application', $staff['related_type']);
        expect_same(17, (int) $staff['related_id']);
        expect_same('staff@example.org', $staff['recipient']);
        expect_true(str_starts_with((string) $staff['subject'], '[RIT Foundation Ghana] '));
        expect_contains('RIT-APP-7K3QX9MD', (string) $staff['body_text']);
        expect_contains('https://www.example.org/admin/applications/17', (string) $staff['body_text']);
        expect_not_contains('Ama', (string) $staff['body_text'] . $staff['subject']);
        expect_not_contains('ama@example.org', (string) $staff['body_text'] . $staff['subject']);
        expect_not_contains("\r", (string) $staff['body_text']);
        expect_not_contains("Digital Skills\nBcc", (string) $staff['body_text']);

        expect_same('confirm.training_application', $confirm['purpose']);
        expect_same('ama@example.org', $confirm['recipient']);
        expect_contains('Hello Ama,', (string) $confirm['body_text']);
        expect_contains('RIT-APP-7K3QX9MD', (string) $confirm['body_text']);
        expect_contains('does not guarantee a place', (string) $confirm['body_text']);
        expect_not_contains('Mensah', (string) $confirm['body_text']);
        expect_same('queued', $confirm['status']);
    } finally {
        Config::set('app.url', $old);
        ob_cleanup($b);
    }
});

test('notifier: confirmations can be switched off', function () {
    if (($r = ob_skip_reason()) !== null) {
        return 'skip';
    }
    $b = ob_baseline();
    try {
        nt_notifier(['notify.email' => 'staff@example.org', 'notify.send_confirmations' => '0'])
            ->volunteerReceived(3, 'RIT-VOL-AAAA2222', 'Kofi Boateng', 'kofi@example.org');
        $rows = nt_rows($b);
        expect_same(1, count($rows));
        expect_same('staff.volunteer', $rows[0]['purpose']);
        expect_same('volunteer_application', $rows[0]['related_type']);
    } finally {
        ob_cleanup($b);
    }
});

test('notifier: without a staff address a skipped row is recorded', function () {
    if (($r = ob_skip_reason()) !== null) {
        return 'skip';
    }
    $b = ob_baseline();
    $old = config('mail.admin_address');
    try {
        Config::set('mail.admin_address', '');
        nt_notifier(['notify.send_confirmations' => '0'])->contactReceived(5, 'RIT-MSG-BBBB3333', 'Hello', 'Esi', 'esi@example.org');
        $rows = nt_rows($b);
        expect_same(1, count($rows));
        expect_same('skipped', $rows[0]['status']);
        expect_same('No staff notification address configured', $rows[0]['last_error']);
        expect_same('staff.contact', $rows[0]['purpose']);
        expect_same(null, $rows[0]['body_text']);

        Config::set('mail.admin_address', 'fallback@example.org');
        nt_notifier(['notify.send_confirmations' => '0'])->contactReceived(6, 'RIT-MSG-CCCC4444', 'Hello', 'Esi', 'esi@example.org');
        $rows = nt_rows($b);
        expect_same('queued', $rows[1]['status']);
        expect_same('fallback@example.org', $rows[1]['recipient']);
    } finally {
        Config::set('mail.admin_address', $old);
        ob_cleanup($b);
    }
});

test('notifier: partnership and contact staff notices carry only the reference', function () {
    if (($r = ob_skip_reason()) !== null) {
        return 'skip';
    }
    $b = ob_baseline();
    try {
        $n = nt_notifier(['notify.email' => 'staff@example.org']);
        $n->partnershipReceived(8, 'RIT-PRT-DDDD5555', 'Acme Foundation', 'Yaw Owusu', 'yaw@acme.test');
        $n->contactReceived(9, 'RIT-MSG-EEEE6666', 'Question about donations', 'Efua Asante', 'efua@example.org');
        $rows = nt_rows($b);
        expect_same(4, count($rows));
        foreach ([$rows[0], $rows[2]] as $staff) {
            foreach (['Yaw', 'Owusu', 'yaw@acme.test', 'Efua', 'Asante', 'efua@example.org'] as $pii) {
                expect_not_contains($pii, (string) $staff['body_text'] . $staff['subject']);
            }
        }
        // Staff emails carry only the reference — no text typed by the public (organisation, subject).
        expect_not_contains('Question about donations', (string) $rows[2]['body_text']);
        expect_not_contains('Acme Foundation', (string) $rows[0]['body_text']);
        expect_contains('Hello Yaw,', (string) $rows[1]['body_text']);
        expect_not_contains('Acme', (string) $rows[1]['body_text']);
    } finally {
        ob_cleanup($b);
    }
});

test('notifier: password reset email', function () {
    if (($r = ob_skip_reason()) !== null) {
        return 'skip';
    }
    $b = ob_baseline();
    try {
        nt_notifier([])->passwordReset("admin@example.org\r\nBcc: x@evil.test", 'Site Admin', "https://www.example.org/admin/reset/abc123\r\n", 60);
        $rows = nt_rows($b);
        expect_same(1, count($rows));
        expect_same('admin.password_reset', $rows[0]['purpose']);
        expect_same('admin', $rows[0]['related_type']);
        expect_not_contains("\n", (string) $rows[0]['recipient']);
        expect_contains('https://www.example.org/admin/reset/abc123', (string) $rows[0]['body_text']);
        expect_contains('60 minutes', (string) $rows[0]['body_text']);
        expect_contains('Hello Site,', (string) $rows[0]['body_text']);
    } finally {
        ob_cleanup($b);
    }
});
