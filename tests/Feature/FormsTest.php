<?php

declare(strict_types=1);

/*
 * End-to-end tests of the public forms (apply, volunteer, partner, contact, donate)
 * against the real application served by PHP's built-in server and the test database.
 * Everything the tests create is removed again by ft_cleanup() at shutdown.
 */

require_once __DIR__ . '/../http.php';

use App\Core\Database;
use App\Models\Program;
use App\Services\Mail\MailMessage;
use App\Services\Mail\MailResult;
use App\Services\Mail\Mailer;
use App\Services\Mail\Outbox;
use App\Services\Mail\Transport;

const FT_TABLES = ['training_applications', 'volunteer_applications', 'partnership_enquiries', 'contact_enquiries'];

/** Null when the HTTP + database tests can run, otherwise the reason to skip. */
function ft_skip(): ?string
{
    static $reason = false;
    if ($reason !== false) {
        return $reason;
    }
    try {
        if (!str_contains((string) config('database.name', ''), 'test')) {
            return $reason = 'test database not configured (.env.testing DB_NAME must contain "test")';
        }
        Database::connection()->fetchValue('SELECT COUNT(*) FROM training_applications');
        if (!function_exists('curl_init')) {
            return $reason = 'the curl extension is required';
        }
        ft_setup();
        return $reason = null;
    } catch (Throwable $e) {
        return $reason = 'test database not available: ' . $e::class;
    }
}

/** @return array{open: int, closed: int, baseline: array<string, int>} */
function ft_setup(): array
{
    static $state = null;
    if ($state !== null) {
        return $state;
    }
    $db = Database::connection();
    $baseline = [];
    foreach ([...FT_TABLES, 'email_outbox'] as $table) {
        $baseline[$table] = (int) $db->fetchValue("SELECT COALESCE(MAX(id), 0) FROM `{$table}`");
    }
    $fixture = static fn (string $slug, string $title, int $open): int => Program::create([
        'title' => $title, 'slug' => $slug, 'summary' => 'Test fixture', 'description' => 'Test fixture',
        'status' => 'published', 'accepts_applications' => $open, 'sort_order' => 9000,
    ]);
    $db->execute("DELETE FROM programs WHERE slug IN ('zz-forms-test-open', 'zz-forms-test-closed')");
    $state = [
        'open' => $fixture('zz-forms-test-open', 'ZZ Forms Test Program', 1),
        'closed' => $fixture('zz-forms-test-closed', 'ZZ Forms Closed Program', 0),
        'baseline' => $baseline,
    ];
    register_shutdown_function('ft_cleanup');
    return $state;
}

function ft_cleanup(): void
{
    $state = ft_setup();
    $db = Database::connection();
    $root = APP_ROOT . '/storage/private/applications';
    $like = ['e' => 'ft-' . ft_run() . '-%'];
    $types = ['training_applications' => 'training_application', 'volunteer_applications' => 'volunteer_application',
        'partnership_enquiries' => 'partnership_enquiry', 'contact_enquiries' => 'contact_enquiry'];
    foreach ($db->fetchAll('SELECT attachment_path FROM training_applications WHERE email LIKE :e AND attachment_path IS NOT NULL', $like) as $row) {
        $file = $root . '/' . $row['attachment_path'];
        if (is_file($file)) {
            @unlink($file);
        }
    }
    foreach ($types as $table => $type) {
        $db->execute("DELETE FROM email_outbox WHERE related_type = :t AND related_id IN (SELECT id FROM `{$table}` WHERE email LIKE :e)", ['t' => $type] + $like);
        $db->execute("DELETE FROM `{$table}` WHERE email LIKE :e", $like);
    }
    $db->execute('DELETE FROM programs WHERE id IN (:a, :b)', ['a' => $state['open'], 'b' => $state['closed']]);
    $db->execute('DELETE FROM rate_limits');
}

/** Unique marker for this test run: lets the tests ignore rows other processes create in the shared test database. */
function ft_run(): string
{
    static $run = null;
    return $run ??= bin2hex(random_bytes(4));
}

/** Rows created by this test run (all fixtures use e-mail addresses ft-<run>-…@forms-test.example). */
function ft_count(string $table): int
{
    return (int) Database::connection()->fetchValue("SELECT COUNT(*) FROM `{$table}` WHERE email LIKE :e", ['e' => 'ft-' . ft_run() . '-%']);
}

/** @return array<string, mixed> The newest row created by this test run. */
function ft_last(string $table): array
{
    return Database::connection()->fetch("SELECT * FROM `{$table}` WHERE email LIKE :e ORDER BY id DESC LIMIT 1", ['e' => 'ft-' . ft_run() . '-%']) ?? [];
}

function ft_email(string $form): string
{
    return 'FT-' . ft_run() . '-' . $form . '@Forms-Test.example';
}

/** @return list<string> files currently stored under storage/private/applications */
function ft_stored_files(): array
{
    $files = [];
    $root = APP_ROOT . '/storage/private/applications';
    if (is_dir($root)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && $f->getFilename() !== '.gitkeep') {
                $files[] = $f->getPathname();
            }
        }
    }
    sort($files);
    return $files;
}

/** The forms under test. 'valid' is a complete valid submission. */
function ft_forms(): array
{
    $state = ft_setup();
    return [
        'volunteer' => [
            'path' => '/volunteer', 'table' => 'volunteer_applications', 'name' => 'full_name', 'prefix' => 'RIT-VOL',
            'valid' => ['full_name' => 'Ama Mensah', 'email' => ft_email('volunteer'), 'phone' => '024 123 4567', 'location' => 'Volta',
                'interest_area' => 'mentoring', 'skills' => 'Teaching', 'availability' => 'Saturdays', 'message' => 'Happy to help.', 'privacy' => '1'],
            'required' => ['full_name', 'email', 'interest_area', 'privacy'],
            'max' => ['full_name' => 150, 'skills' => 2000, 'availability' => 255, 'message' => 2000],
            'text' => ['message', 'skills', 'availability', 'full_name'],
        ],
        'partner' => [
            'path' => '/partner', 'table' => 'partnership_enquiries', 'name' => 'contact_name', 'prefix' => 'RIT-PAR',
            'valid' => ['organization_name' => 'Acme Ghana Ltd', 'contact_name' => 'Kofi Boateng', 'email' => ft_email('partner'), 'phone' => '+233 24 000 1111',
                'website' => 'https://acme.example', 'partnership_type' => 'training', 'message' => 'We would like to sponsor a training cohort.', 'privacy' => '1'],
            'required' => ['organization_name', 'contact_name', 'email', 'partnership_type', 'message', 'privacy'],
            'max' => ['organization_name' => 190, 'contact_name' => 150, 'message' => 3000],
            'text' => ['message', 'organization_name', 'contact_name'],
        ],
        'contact' => [
            'path' => '/contact', 'table' => 'contact_enquiries', 'name' => 'full_name', 'prefix' => 'RIT-MSG',
            'valid' => ['full_name' => 'Efua Owusu', 'email' => ft_email('contact'), 'phone' => '', 'subject' => 'Question about programs',
                'message' => 'Please tell me more about your programs.', 'privacy' => '1'],
            'required' => ['full_name', 'email', 'subject', 'message', 'privacy'],
            'max' => ['full_name' => 150, 'subject' => 190, 'message' => 3000],
            'text' => ['message', 'subject', 'full_name'],
        ],
        'apply' => [
            'path' => '/apply', 'table' => 'training_applications', 'name' => 'full_name', 'prefix' => 'RIT-APP',
            'valid' => ['full_name' => 'Yaa Asantewaa', 'email' => ft_email('apply'), 'phone' => '024 123 4567', 'age_range' => '18-24', 'location' => 'Ashanti',
                'program_id' => (string) $state['open'], 'background' => 'SHS', 'reason' => 'I want to learn practical skills for work.',
                'availability' => 'weekdays', 'privacy' => '1'],
            'required' => ['full_name', 'email', 'phone', 'age_range', 'location', 'program_id', 'reason', 'availability', 'privacy'],
            'max' => ['full_name' => 150, 'background' => 2000, 'reason' => 2000],
            'text' => ['reason', 'background', 'full_name'],
        ],
    ];
}

/** Loads a form and returns [html, csrf, nonce]. */
function ft_load(string $path, CookieJar $jar): array
{
    $page = Http::get($path, $jar);
    return [$page->body, csrf_from($page->body), nonce_from($page->body), $page->status];
}

/** @param array<string, mixed> $fields */
function ft_submit(string $path, CookieJar $jar, array $fields, ?string $csrf = null, ?string $nonce = null, ?array $loaded = null): HttpResponse
{
    $loaded ??= ft_load($path, $jar);
    $body = $fields + ['_token' => $csrf ?? $loaded[1], '_form_nonce' => $nonce ?? $loaded[2], 'website_url' => ''];
    return Http::post($path, $body, $jar);
}

/** Outbox rows for a submission, as [purpose => status]. */
function ft_outbox(string $table, string $reference): array
{
    $type = ['training_applications' => 'training_application', 'volunteer_applications' => 'volunteer_application',
        'partnership_enquiries' => 'partnership_enquiry', 'contact_enquiries' => 'contact_enquiry'][$table];
    $id = (int) Database::connection()->fetchValue("SELECT id FROM `{$table}` WHERE reference = :r", ['r' => $reference]);
    $out = [];
    foreach (Database::connection()->fetchAll('SELECT purpose, status FROM email_outbox WHERE related_type = :t AND related_id = :i', ['t' => $type, 'i' => $id]) as $row) {
        $out[(string) $row['purpose']] = (string) $row['status'];
    }
    return $out;
}

foreach (['volunteer', 'partner', 'contact', 'apply'] as $ftForm) {
    $thanks = static fn (): string => ft_forms()[$ftForm]['path'] . '/thank-you';

    test("forms[$ftForm]: valid submission stores the row, queues emails and shows only the reference", function () use ($ftForm) {
        if (($r = ft_skip()) !== null) { echo "        ($r)\n"; return 'skip'; }
        reset_rate_limits();
        $f = ft_forms()[$ftForm];
        $jar = new CookieJar();
        $before = ft_count($f['table']);
        $res = ft_submit($f['path'], $jar, $f['valid']);
        expect_same(303, $res->status);
        expect_same($f['path'] . '/thank-you', $res->header('location'));
        expect_same($before + 1, ft_count($f['table']));
        $row = ft_last($f['table']);
        expect_true(preg_match('/^' . $f['prefix'] . '-[A-Z0-9]{8}$/', (string) $row['reference']) === 1, 'reference format');
        expect_true(preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', (string) $row['privacy_accepted_at']) === 1, 'privacy_accepted_at');
        expect_same(strtolower($f['valid']['email']), $row['email'], 'email is lower-cased');
        expect_same(64, strlen((string) $row['idempotency_key']));
        foreach ($row as $column => $value) {
            expect_true(!in_array($column, ['ip', 'ip_address', 'user_agent'], true), "no {$column} column stored");
        }
        $mail = ft_outbox($f['table'], (string) $row['reference']);
        expect_true(count($mail) === 2, 'staff + confirmation rows queued, got ' . json_encode($mail));
        foreach ($mail as $purpose => $status) {
            expect_true(in_array($status, ['queued', 'handed_off', 'skipped'], true), "{$purpose} is {$status}");
        }
        $page = Http::get($f['path'] . '/thank-you', $jar);
        expect_same(200, $page->status);
        expect_contains((string) $row['reference'], $page->body);
        expect_contains('queued a confirmation email', $page->body);
        expect_not_contains('delivered', $page->body);
        expect_not_contains($f['valid'][$f['name']], $page->body, 'thank-you must not echo submitted content');
        if ($ftForm === 'apply') {
            expect_contains('does not guarantee a place on a program', $page->body);
        }
        // A reload keeps showing the same reference.
        expect_contains((string) $row['reference'], Http::get($f['path'] . '/thank-you', $jar)->body);
    });

    test("forms[$ftForm]: thank-you without a submission redirects to the form", function () use ($ftForm) {
        if (($r = ft_skip()) !== null) { return 'skip'; }
        $f = ft_forms()[$ftForm];
        $res = Http::get($f['path'] . '/thank-you', new CookieJar());
        expect_same(303, $res->status);
        expect_same($f['path'], $res->header('location'));
    });

    test("forms[$ftForm]: missing required fields show errors and store nothing", function () use ($ftForm) {
        if (($r = ft_skip()) !== null) { return 'skip'; }
        reset_rate_limits();
        $f = ft_forms()[$ftForm];
        foreach ($f['required'] as $field) {
            $jar = new CookieJar();
            $fields = $f['valid'];
            unset($fields[$field]);
            $before = ft_count($f['table']);
            $res = ft_submit($f['path'], $jar, $fields);
            expect_same(303, $res->status, "{$field} missing:");
            expect_same($f['path'], $res->header('location'), "{$field} missing:");
            expect_same($before, ft_count($f['table']), "{$field} missing stored a row");
            $page = Http::get($f['path'], $jar);
            expect_contains('id="error-summary"', $page->body, "{$field} missing:");
            expect_contains('href="#field-' . $field . '"', $page->body, "{$field} missing:");
            reset_rate_limits();
        }
    });

    test("forms[$ftForm]: invalid email is rejected", function () use ($ftForm) {
        if (($r = ft_skip()) !== null) { return 'skip'; }
        reset_rate_limits();
        $f = ft_forms()[$ftForm];
        $jar = new CookieJar();
        $before = ft_count($f['table']);
        $res = ft_submit($f['path'], $jar, ['email' => 'not-an-email'] + $f['valid']);
        expect_same(303, $res->status);
        expect_same($before, ft_count($f['table']));
        expect_contains('valid email address', Http::get($f['path'], $jar)->body);
    });

    test("forms[$ftForm]: input over the maximum length is rejected", function () use ($ftForm) {
        if (($r = ft_skip()) !== null) { return 'skip'; }
        reset_rate_limits();
        $f = ft_forms()[$ftForm];
        foreach ($f['max'] as $field => $max) {
            $jar = new CookieJar();
            $before = ft_count($f['table']);
            $res = ft_submit($f['path'], $jar, [$field => str_repeat('a', $max + 1)] + $f['valid']);
            expect_same(303, $res->status, "{$field}:");
            expect_same($f['path'], $res->header('location'), "{$field}:");
            expect_same($before, ft_count($f['table']), "{$field} over {$max} was stored");
            expect_contains('no more than ' . $max . ' characters', Http::get($f['path'], $jar)->body, "{$field}:");
            reset_rate_limits();
        }
    });

    test("forms[$ftForm]: missing or wrong CSRF token gives 419 and stores nothing", function () use ($ftForm) {
        if (($r = ft_skip()) !== null) { return 'skip'; }
        reset_rate_limits();
        $f = ft_forms()[$ftForm];
        $jar = new CookieJar();
        $loaded = ft_load($f['path'], $jar);
        $before = ft_count($f['table']);
        $none = Http::post($f['path'], $f['valid'] + ['_form_nonce' => $loaded[2], 'website_url' => ''], $jar);
        expect_same(419, $none->status);
        $wrong = ft_submit($f['path'], $jar, $f['valid'], str_repeat('0', 64), null, $loaded);
        expect_same(419, $wrong->status);
        expect_same($before, ft_count($f['table']));
    });

    test("forms[$ftForm]: the same form posted twice creates exactly one record", function () use ($ftForm) {
        if (($r = ft_skip()) !== null) { return 'skip'; }
        reset_rate_limits();
        $f = ft_forms()[$ftForm];
        $jar = new CookieJar();
        $loaded = ft_load($f['path'], $jar);
        $before = ft_count($f['table']);
        $first = ft_submit($f['path'], $jar, $f['valid'], null, null, $loaded);
        $second = ft_submit($f['path'], $jar, $f['valid'], null, null, $loaded);
        expect_same(303, $first->status);
        expect_same($f['path'] . '/thank-you', $second->header('location'));
        expect_same($before + 1, ft_count($f['table']), 'duplicate submission created a second record');
        // Even without the session (a second device re-posting the same nonce) the UNIQUE key holds.
        $third = ft_submit($f['path'], new CookieJar(), $f['valid'], null, $loaded[2], ['', '', '']);
        expect_true(in_array($third->status, [303, 419], true));
        expect_same($before + 1, ft_count($f['table']));
    });

    test("forms[$ftForm]: a filled honeypot gives 422 and stores nothing", function () use ($ftForm) {
        if (($r = ft_skip()) !== null) { return 'skip'; }
        reset_rate_limits();
        $f = ft_forms()[$ftForm];
        $jar = new CookieJar();
        $loaded = ft_load($f['path'], $jar);
        $before = ft_count($f['table']);
        $res = Http::post($f['path'], $f['valid'] + ['_token' => $loaded[1], '_form_nonce' => $loaded[2], 'website_url' => 'http://spam.example'], $jar);
        expect_same(422, $res->status);
        expect_same($before, ft_count($f['table']));
        // The honeypot must be present (and hidden by class, not inline style) on the form.
        expect_contains('name="website_url"', $loaded[0]);
        expect_contains('class="hp-field"', $loaded[0]);
    });

    test("forms[$ftForm]: validation errors never lock people out; the 6th successful submission answers 429", function () use ($ftForm) {
        if (($r = ft_skip()) !== null) { return 'skip'; }
        reset_rate_limits();
        $f = ft_forms()[$ftForm];
        $jar = new CookieJar();
        $loaded = ft_load($f['path'], $jar);
        $invalid = [];
        for ($i = 0; $i < 6; $i++) {
            $invalid[] = ft_submit($f['path'], $jar, ['email' => 'bad'] + $f['valid'], null, null, $loaded)->status;
        }
        expect_same([303, 303, 303, 303, 303, 303], $invalid, 'someone correcting mistakes is never throttled');
        $valid = [];
        for ($i = 0; $i < 6; $i++) {
            $valid[] = ft_submit($f['path'], $jar, $f['valid'])->status; // fresh form (new nonce) each time
        }
        expect_same([303, 303, 303, 303, 303, 429], $valid);
        reset_rate_limits();
    });

    test("forms[$ftForm]: markup is stored but never echoed raw", function () use ($ftForm) {
        if (($r = ft_skip()) !== null) { return 'skip'; }
        reset_rate_limits();
        $f = ft_forms()[$ftForm];
        $xss = '<script>alert(1)</script>';
        // 1) valid submission carrying the payload: stored verbatim, thank-you shows none of it.
        $jar = new CookieJar();
        $fields = $f['valid'];
        foreach ($f['text'] as $field) {
            if (isset($fields[$field]) && $fields[$field] !== '') {
                $fields[$field] = $xss . ' ' . $fields[$field];
            }
        }
        $res = ft_submit($f['path'], $jar, $fields);
        expect_same($f['path'] . '/thank-you', $res->header('location'));
        $row = ft_last($f['table']);
        expect_contains($xss, (string) $row[$f['text'][0]], 'payload is stored as text');
        $thanks = Http::get($f['path'] . '/thank-you', $jar);
        expect_not_contains('<script>alert', $thanks->body);
        expect_not_contains('alert(1)', $thanks->body);
        // 2) invalid submission carrying the payload: the re-rendered form escapes the old input.
        reset_rate_limits();
        $jar2 = new CookieJar();
        $res2 = ft_submit($f['path'], $jar2, ['email' => 'bad'] + $fields);
        expect_same($f['path'], $res2->header('location'));
        $form = Http::get($f['path'], $jar2)->body;
        expect_not_contains('<script>alert(1)</script>', $form);
        expect_contains('&lt;script&gt;alert(1)&lt;/script&gt;', $form);
    });
}

/* A duplicate that slips past the session check (e.g. a second tab or device re-posting the same form) must hit
   the UNIQUE idempotency key, resolve to the existing record, create nothing and leave no stored file behind. */
foreach (['volunteer', 'partner', 'contact', 'apply'] as $ftForm) {
    test("forms[$ftForm]: a concurrent duplicate resolves to the existing record (UNIQUE key path)", function () use ($ftForm) {
        if (($r = ft_skip()) !== null) { return 'skip'; }
        reset_rate_limits();
        $f = ft_forms()[$ftForm];
        $model = ['volunteer' => App\Models\VolunteerApplication::class, 'partner' => App\Models\PartnershipEnquiry::class,
            'contact' => App\Models\ContactEnquiry::class, 'apply' => App\Models\TrainingApplication::class][$ftForm];
        $jar = new CookieJar();
        $loaded = ft_load($f['path'], $jar);
        $key = hash('sha256', $ftForm . '|' . $loaded[2]);
        $data = $f['valid'];
        unset($data['privacy']);
        if ($ftForm === 'apply') {
            $data += ['program_title' => 'ZZ Forms Test Program', 'is_adult' => 1];
        }
        $reference = $model::newReference();
        $model::create($data + ['reference' => $reference, 'privacy_accepted_at' => gmdate('Y-m-d H:i:s'), 'idempotency_key' => $key]);
        $before = ft_count($f['table']);
        $filesBefore = ft_stored_files();
        $fields = $f['valid'];
        if ($ftForm === 'apply') {
            $fields['attachment'] = new CURLFile(ft_tmp("%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n", '.pdf'), 'application/pdf', 'dup.pdf');
        }
        $res = ft_submit($f['path'], $jar, $fields, null, null, $loaded);
        expect_same($f['path'] . '/thank-you', $res->header('location'));
        expect_same($before, ft_count($f['table']), 'no second record');
        expect_contains($reference, Http::get($f['path'] . '/thank-you', $jar)->body, 'the existing reference is shown');
        expect_same([], array_values(array_diff(ft_stored_files(), $filesBefore)), 'no orphaned attachment');
    });
}

/* ---------------------------------------------------------------- apply only */

test('apply: a program that is not open cannot be applied to', function () {
    if (($r = ft_skip()) !== null) { return 'skip'; }
    reset_rate_limits();
    $f = ft_forms()['apply'];
    $jar = new CookieJar();
    $before = ft_count($f['table']);
    $res = ft_submit('/apply', $jar, ['program_id' => (string) ft_setup()['closed']] + $f['valid']);
    expect_same('/apply', $res->header('location'));
    expect_same($before, ft_count($f['table']));
    expect_contains('Applications for this program have closed', Http::get('/apply', $jar)->body);
    // Unknown ids behave the same.
    reset_rate_limits();
    $res = ft_submit('/apply', $jar, ['program_id' => '999999'] + $f['valid']);
    expect_same('/apply', $res->header('location'));
    expect_same($before, ft_count($f['table']));
});

test('apply: program_title is a snapshot taken from the open program', function () {
    if (($r = ft_skip()) !== null) { return 'skip'; }
    reset_rate_limits();
    $f = ft_forms()['apply'];
    ft_submit('/apply', new CookieJar(), $f['valid']);
    $row = ft_last('training_applications');
    expect_same('ZZ Forms Test Program', $row['program_title']);
    expect_same(ft_setup()['open'], (int) $row['program_id']);
    expect_same(1, (int) $row['is_adult']);
    expect_same(null, $row['guardian_name']);
});

test('apply: the form lists open programs, preselects ?program= and keeps the guardian block visible without JavaScript', function () {
    if (($r = ft_skip()) !== null) { return 'skip'; }
    $page = Http::get('/apply?program=zz-forms-test-open');
    expect_same(200, $page->status);
    expect_contains('<option value="' . ft_setup()['open'] . '" selected>ZZ Forms Test Program</option>', $page->body);
    expect_not_contains('ZZ Forms Closed Program', $page->body);
    expect_contains('data-show-when="age_range=under-15|15-17"', $page->body);
    expect_not_contains('hidden', substr($page->body, (int) strpos($page->body, 'data-show-when="age_range'), 40), 'block is hidden only by JavaScript');
    expect_not_contains(' novalidate', $page->body);
    expect_contains('data-once', $page->body);
    expect_contains('enctype="multipart/form-data"', $page->body);
    expect_contains('Accepted formats: PDF, JPG, PNG (max. 5 MB)', $page->body);
});

test('apply: an applicant under 18 needs guardian name, telephone and consent', function () {
    if (($r = ft_skip()) !== null) { return 'skip'; }
    reset_rate_limits();
    $f = ft_forms()['apply'];
    $minor = ['age_range' => '15-17'] + $f['valid'];
    foreach (['guardian_name', 'guardian_phone', 'guardian_consent'] as $missing) {
        $jar = new CookieJar();
        $fields = $minor + ['guardian_name' => 'Nana Asantewaa', 'guardian_phone' => '024 999 8888', 'guardian_consent' => '1'];
        unset($fields[$missing]);
        $before = ft_count($f['table']);
        $res = ft_submit('/apply', $jar, $fields);
        expect_same('/apply', $res->header('location'), "{$missing} missing:");
        expect_same($before, ft_count($f['table']), "{$missing} missing stored a row");
        expect_contains('href="#field-' . $missing . '"', Http::get('/apply', $jar)->body, "{$missing}:");
        reset_rate_limits();
    }
});

test('apply: a minor with a guardian is stored with is_adult = 0 and the guardian details', function () {
    if (($r = ft_skip()) !== null) { return 'skip'; }
    reset_rate_limits();
    $f = ft_forms()['apply'];
    $res = ft_submit('/apply', new CookieJar(), ['age_range' => '15-17', 'guardian_name' => 'Nana Asantewaa', 'guardian_phone' => '024 999 8888', 'guardian_consent' => '1'] + $f['valid']);
    expect_same('/apply/thank-you', $res->header('location'));
    $row = ft_last('training_applications');
    expect_same(0, (int) $row['is_adult']);
    expect_same('Nana Asantewaa', $row['guardian_name']);
    expect_same('024 999 8888', $row['guardian_phone']);
    expect_same(1, (int) $row['guardian_consent']);
});

test('apply: guardian details sent for an adult are ignored', function () {
    if (($r = ft_skip()) !== null) { return 'skip'; }
    reset_rate_limits();
    $f = ft_forms()['apply'];
    ft_submit('/apply', new CookieJar(), ['guardian_name' => 'Someone', 'guardian_phone' => '024 999 8888', 'guardian_consent' => '1'] + $f['valid']);
    $row = ft_last('training_applications');
    expect_same(null, $row['guardian_name']);
    expect_same(null, $row['guardian_phone']);
    expect_same(0, (int) $row['guardian_consent']);
});

function ft_tmp(string $contents, string $suffix): string
{
    $path = sys_get_temp_dir() . '/ft-' . bin2hex(random_bytes(6)) . $suffix;
    file_put_contents($path, $contents);
    register_shutdown_function(static function () use ($path): void { @unlink($path); });
    return $path;
}

test('apply: a small real PDF is accepted and stored privately outside public/', function () {
    if (($r = ft_skip()) !== null) { return 'skip'; }
    reset_rate_limits();
    $f = ft_forms()['apply'];
    $pdf = ft_tmp("%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n", '.pdf');
    $before = ft_stored_files();
    $res = ft_submit('/apply', new CookieJar(), ['attachment' => new CURLFile($pdf, 'application/pdf', 'My CV.pdf')] + $f['valid']);
    expect_same('/apply/thank-you', $res->header('location'));
    $row = ft_last('training_applications');
    expect_true(preg_match('#^\d{4}/\d{2}/[a-f0-9]{32}\.pdf$#', (string) $row['attachment_path']) === 1, 'stored path ' . $row['attachment_path']);
    expect_same('My CV.pdf', $row['attachment_name']);
    expect_same('application/pdf', $row['attachment_mime']);
    expect_true((int) $row['attachment_size'] > 0);
    $absolute = APP_ROOT . '/storage/private/applications/' . $row['attachment_path'];
    expect_true(is_file($absolute), 'file exists in private storage');
    expect_false(str_contains(realpath($absolute) ?: '', '/public/'), 'not under public/');
    expect_true(!file_exists(APP_ROOT . '/public/' . $row['attachment_path']) && !file_exists(APP_ROOT . '/public/uploads/' . $row['attachment_path']));
    expect_same([$absolute], array_values(array_diff(ft_stored_files(), $before)));
    expect_same(404, Http::get('/storage/private/applications/' . $row['attachment_path'])->status);
});

test('apply: a PHP script renamed .pdf is refused and nothing is stored', function () {
    if (($r = ft_skip()) !== null) { return 'skip'; }
    reset_rate_limits();
    $f = ft_forms()['apply'];
    $evil = ft_tmp("<?php echo 'pwned'; ?>\n", '.pdf');
    $before = ft_stored_files();
    $rows = ft_count('training_applications');
    $jar = new CookieJar();
    $res = ft_submit('/apply', $jar, ['attachment' => new CURLFile($evil, 'application/pdf', 'cv.pdf')] + $f['valid']);
    expect_same('/apply', $res->header('location'));
    expect_same($rows, ft_count('training_applications'));
    expect_same([], array_values(array_diff(ft_stored_files(), $before)), 'no file may be left behind');
    expect_contains('Only PDF, JPG or PNG files are allowed', Http::get('/apply', $jar)->body);
});

test('apply: an oversized document is refused', function () {
    if (($r = ft_skip()) !== null) { return 'skip'; }
    reset_rate_limits();
    $f = ft_forms()['apply'];
    $big = ft_tmp("%PDF-1.4\n" . str_repeat('A', 6 * 1024 * 1024) . "\n%%EOF\n", '.pdf');
    $before = ft_stored_files();
    $rows = ft_count('training_applications');
    $jar = new CookieJar();
    $res = ft_submit('/apply', $jar, ['attachment' => new CURLFile($big, 'application/pdf', 'big.pdf')] + $f['valid']);
    expect_same('/apply', $res->header('location'));
    expect_same($rows, ft_count('training_applications'));
    expect_same([], array_values(array_diff(ft_stored_files(), $before)), 'no file may be left behind');
    expect_contains('too large', Http::get('/apply', $jar)->body);
});

/* ---------------------------------------------------------------- donate (no payment provider) */

test('donate: without a provider the page shows the notice and no payment form', function () {
    if (($r = ft_skip()) !== null) { return 'skip'; }
    $page = Http::get('/donate');
    expect_same(200, $page->status);
    expect_contains('Online donations are not available yet', $page->body);
    expect_not_contains('name="amount"', $page->body);
    expect_not_contains('Continue to secure payment', $page->body);
    expect_contains('href="/volunteer"', $page->body);
    expect_contains('href="/partner"', $page->body);
});

test('donate: POST while unavailable redirects with a notice and starts nothing', function () {
    if (($r = ft_skip()) !== null) { return 'skip'; }
    reset_rate_limits();
    $jar = new CookieJar();
    $csrf = csrf_from(Http::get('/contact', $jar)->body);
    $before = (int) Database::connection()->fetchValue('SELECT COUNT(*) FROM donations');
    $res = Http::post('/donate', ['_token' => $csrf, 'amount' => '100', 'privacy' => '1'], $jar);
    expect_same(303, $res->status);
    expect_same('/donate', $res->header('location'));
    expect_same($before, (int) Database::connection()->fetchValue('SELECT COUNT(*) FROM donations'));
    expect_contains('not available yet', Http::get('/donate', $jar)->body);
});

test('donate: webhook is 404 without a configured provider and callback needs a known reference', function () {
    if (($r = ft_skip()) !== null) { return 'skip'; }
    expect_same(404, Http::post('/webhooks/payments/none', ['x' => '1'], null, ['Content-Type' => 'application/json'])->status);
    expect_same(404, Http::post('/webhooks/payments/example', ['x' => '1'])->status);
    expect_same(404, Http::get('/donate/callback?reference=RIT-DON-ZZZZZZZZ')->status);
    expect_same(404, Http::get('/donate/callback?reference=nonsense')->status);
    expect_same(404, Http::get('/donate/callback')->status);
});

test('contact: page shows the contact details and no map unless a verified embed link is set', function () {
    if (($r = ft_skip()) !== null) { return 'skip'; }
    $page = Http::get('/contact');
    expect_same(200, $page->status);
    expect_contains('href="mailto:', $page->body);
    expect_contains('href="tel:', $page->body);
    expect_not_contains('<iframe', $page->body);
    expect_contains("frame-src 'none'", (string) $page->header('content-security-policy'));
});

/* ---------------------------------------------------------------- email failure */

final class FormsFailingTransport implements Transport
{
    public int $sent = 0;

    public function send(MailMessage $message): MailResult
    {
        $this->sent++;
        return MailResult::failed('Could not connect', true);
    }
}

test('email failure: a failing mail server never loses the submission', function () {
    if (($r = ft_skip()) !== null) { return 'skip'; }
    $db = Database::connection();
    Outbox::resetQueued();
    [$id, $reference] = $db->transaction(function () {
        $reference = App\Models\ContactEnquiry::newReference();
        $id = App\Models\ContactEnquiry::create([
            'reference' => $reference, 'full_name' => 'Failing Mail', 'email' => ft_email('failmail'), 'subject' => 'S', 'message' => 'Message body here',
            'privacy_accepted_at' => gmdate('Y-m-d H:i:s'), 'idempotency_key' => hash('sha256', 'ft-' . $reference),
        ]);
        (new App\Services\Notifier(static fn (string $k, string $d): string => $k === 'notify.email' ? 'staff@example.org' : '1'))
            ->contactReceived($id, $reference, 'S', 'Failing Mail', ft_email('failmail'));
        return [$id, $reference];
    });
    $queued = Outbox::queuedThisRequest();
    expect_same(2, count($queued));
    $transport = new FormsFailingTransport();
    $counts = Outbox::process($queued, 10, new Mailer($transport));
    expect_same(1, $counts['failed'], 'the first failure marks a connection failure and stops the batch');
    expect_same(1, $transport->sent);
    $row = $db->fetch('SELECT * FROM contact_enquiries WHERE id = :i', ['i' => $id]);
    expect_same($reference, $row['reference'], 'the submission row is intact');
    expect_same('Failing Mail', $row['full_name']);
    $states = array_column($db->fetchAll('SELECT status FROM email_outbox WHERE related_type = :t AND related_id = :i', ['t' => 'contact_enquiry', 'i' => $id]), 'status');
    sort($states);
    expect_same(['failed', 'queued'], $states, 'one failed, the other left queued for the next run');
    Outbox::resetQueued();
});

test('email: with the log driver the hand-off of a real submission is not marked failed', function () {
    if (($r = ft_skip()) !== null) { return 'skip'; }
    reset_rate_limits();
    $f = ft_forms()['contact'];
    ft_submit('/contact', new CookieJar(), $f['valid']);
    $ref = (string) ft_last('contact_enquiries')['reference'];
    // The server hands the mail off after sending the response; give it a moment.
    $states = [];
    for ($i = 0; $i < 100; $i++) { // up to 15 s on a slow machine
        $states = array_values(ft_outbox('contact_enquiries', $ref));
        if (!in_array('queued', $states, true)) {
            break;
        }
        usleep(150_000);
    }
    expect_true($states !== [] && array_diff($states, ['handed_off', 'skipped']) === [], 'statuses: ' . json_encode($states));
});
