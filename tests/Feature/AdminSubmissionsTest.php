<?php

declare(strict_types=1);

/*
 * Private submissions: training applications, volunteers, partnerships, contact messages,
 * attachments, deletion, audit and the dashboard. Uses the helpers of AdminAuthTest.
 * Test rows use references starting with RIT-TST and are removed afterwards.
 */

require_once __DIR__ . '/../http.php';
require_once __DIR__ . '/AdminAuthTest.php';

use App\Core\Database;
use App\Models\ContactEnquiry;
use App\Models\PartnershipEnquiry;
use App\Models\TrainingApplication;
use App\Models\VolunteerApplication;
use App\Services\DocumentStore;

function as_cleanup(): void
{
    $db = Database::connection();
    foreach (['training_applications', 'volunteer_applications', 'partnership_enquiries', 'contact_enquiries'] as $table) {
        $db->execute("DELETE FROM `{$table}` WHERE reference LIKE 'RIT-TST-%'");
    }
    $db->execute("DELETE FROM email_outbox WHERE subject LIKE 'TEST-AS %'");
    $db->execute("DELETE FROM admin_audit_logs WHERE summary LIKE 'RIT-TST-%'");
    aa_cleanup();
}

function as_ref(): string
{
    return 'RIT-TST-' . strtoupper(bin2hex(random_bytes(4)));
}

/** @return array{id: int, reference: string} */
function as_application(array $override = []): array
{
    $ref = as_ref();
    $id = TrainingApplication::create($override + [
        'reference' => $ref, 'program_id' => null, 'program_title' => 'Test Program', 'full_name' => 'Test Applicant One',
        'email' => 'applicant-' . strtolower($ref) . '@example.test', 'phone' => '+233200000000', 'age_range' => '18-24', 'is_adult' => 1,
        'location' => 'Volta', 'reason' => 'I want to learn.', 'privacy_accepted_at' => gmdate('Y-m-d H:i:s'),
    ]);
    return ['id' => $id, 'reference' => $ref];
}

function as_pdf_attachment(int $applicationId): string
{
    $tmp = tempnam(sys_get_temp_dir(), 'astest');
    file_put_contents($tmp, "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");
    $doc = (new DocumentStore(null, false))->store(['name' => 'My CV – Ama.pdf', 'type' => 'application/pdf', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => (int) filesize($tmp)]);
    @unlink($tmp);
    TrainingApplication::update($applicationId, ['attachment_path' => $doc->path, 'attachment_name' => $doc->originalName, 'attachment_size' => $doc->size, 'attachment_mime' => $doc->mime]);
    return $doc->path;
}

test('applications: list, search and SQL-injection-looking input', function (): void {
    reset_rate_limits();
    try {
        [$jar] = aa_session('applications_officer');
        $a = as_application();
        $b = as_application(['full_name' => 'Test Applicant Two']);
        $list = Http::get('/admin/applications', $jar);
        expect_same(200, $list->status);
        expect_contains($a['reference'], $list->body);
        expect_contains($b['reference'], $list->body);
        $search = Http::get('/admin/applications?q=' . rawurlencode('Applicant Two'), $jar);
        expect_contains($b['reference'], $search->body);
        expect_not_contains($a['reference'], $search->body);
        $inj = Http::get('/admin/applications?q=' . rawurlencode("' OR '1'='1"), $jar);
        expect_same(200, $inj->status);
        expect_not_contains($a['reference'], $inj->body);
        expect_not_contains($b['reference'], $inj->body);
        foreach (['status=%27%20OR%201=1', 'program=1%20OR%201=1', 'from=2024-13-45&to=x', 'page=-5', 'page=99999999999'] as $qs) {
            expect_same(200, Http::get('/admin/applications?' . $qs, $jar)->status, $qs);
        }
        expect_same(200, Http::get('/admin/applications?status=new&q=' . $a['reference'], $jar)->status);
    } finally {
        as_cleanup();
    }
});

test('applications: CSRF is required, status change is saved and audited', function (): void {
    reset_rate_limits();
    try {
        [$jar, $admin] = aa_session('applications_officer');
        $app = as_application();
        $id = $app['id'];
        $noToken = Http::post("/admin/applications/{$id}/status", ['status' => 'accepted'], $jar);
        expect_same(419, $noToken->status);
        expect_same('new', TrainingApplication::find($id)['status']);

        $show = Http::get("/admin/applications/{$id}", $jar);
        expect_same(200, $show->status);
        expect_contains($app['reference'], $show->body);
        $token = csrf_from($show->body);
        $res = Http::post("/admin/applications/{$id}/status", ['_token' => $token, 'status' => 'under_review'], $jar);
        expect_same(303, $res->status);
        $row = TrainingApplication::find($id);
        expect_same('under_review', $row['status']);
        expect_same($admin['id'], (int) $row['status_changed_by']);
        $audit = Database::connection()->fetch("SELECT summary FROM admin_audit_logs WHERE action = 'application.status_changed' AND entity_id = :id", ['id' => $id]);
        expect_true($audit !== null, 'audit row written');
        expect_contains($app['reference'], $audit['summary']);
        expect_not_contains('Test Applicant', $audit['summary']);
        expect_true(Database::connection()->fetchValue("SELECT COUNT(*) FROM admin_audit_logs WHERE action = 'application.viewed' AND entity_id = :id", ['id' => $id]) >= 1);
        // Invalid status is refused without changing anything.
        Http::post("/admin/applications/{$id}/status", ['_token' => $token, 'status' => 'hacked'], $jar);
        expect_same('under_review', TrainingApplication::find($id)['status']);
        // Unknown id.
        expect_same(404, Http::get('/admin/applications/99999999', $jar)->status);
    } finally {
        as_cleanup();
    }
});

test('applications: notes are saved and rendered escaped', function (): void {
    reset_rate_limits();
    try {
        [$jar] = aa_session('applications_officer');
        $app = as_application();
        $payload = '<script>alert(1)</script><img src=x onerror=alert(2)>';
        $token = csrf_from(Http::get("/admin/applications/{$app['id']}", $jar)->body);
        $res = Http::post("/admin/applications/{$app['id']}/notes", ['_token' => $token, 'admin_notes' => $payload], $jar);
        expect_same(303, $res->status);
        expect_same($payload, TrainingApplication::find($app['id'])['admin_notes']);
        $page = Http::get("/admin/applications/{$app['id']}", $jar)->body;
        expect_contains('&lt;script&gt;alert(1)&lt;/script&gt;', $page);
        expect_not_contains('<script>alert(1)', $page);
        expect_not_contains('<img src=x', $page);
        // Over-long notes are refused.
        Http::post("/admin/applications/{$app['id']}/notes", ['_token' => $token, 'admin_notes' => str_repeat('a', 5001)], $jar);
        expect_same($payload, TrainingApplication::find($app['id'])['admin_notes']);
    } finally {
        as_cleanup();
    }
});

test('applications: attachment download needs sign-in and is served as an attachment', function (): void {
    reset_rate_limits();
    $path = null;
    try {
        [$jar] = aa_session('applications_officer');
        $app = as_application();
        $path = as_pdf_attachment($app['id']);
        $anon = Http::get("/admin/applications/{$app['id']}/attachment");
        expect_same(303, $anon->status);
        expect_same('/admin/login', $anon->header('Location'));
        [$editor] = aa_session('content_editor');
        expect_same(403, Http::get("/admin/applications/{$app['id']}/attachment", $editor)->status);

        $res = Http::get("/admin/applications/{$app['id']}/attachment", $jar);
        expect_same(200, $res->status);
        expect_same('application/pdf', $res->header('Content-Type'));
        expect_contains('attachment; filename="', (string) $res->header('Content-Disposition'));
        expect_contains($app['reference'] . '-My_CV', (string) $res->header('Content-Disposition'));
        expect_contains("filename*=UTF-8''" . rawurlencode($app['reference'] . '-My CV – Ama.pdf'), (string) $res->header('Content-Disposition'));
        expect_same('nosniff', $res->header('X-Content-Type-Options'));
        expect_contains('no-store', (string) $res->header('Cache-Control'));
        expect_same('%PDF-', substr($res->body, 0, 5));
        expect_true(Database::connection()->fetchValue("SELECT COUNT(*) FROM admin_audit_logs WHERE action = 'application.attachment_downloaded' AND entity_id = :id", ['id' => $app['id']]) >= 1);
        // The detail page links to it.
        expect_contains("/admin/applications/{$app['id']}/attachment", Http::get("/admin/applications/{$app['id']}", $jar)->body);
        // An application without a file is a 404.
        $none = as_application();
        expect_same(404, Http::get("/admin/applications/{$none['id']}/attachment", $jar)->status);
    } finally {
        (new DocumentStore())->delete($path);
        as_cleanup();
    }
});

test('applications: delete removes the row, the file and queued emails', function (): void {
    reset_rate_limits();
    try {
        [$jar] = aa_session('applications_officer');
        $app = as_application();
        $path = as_pdf_attachment($app['id']);
        $store = new DocumentStore();
        expect_true($store->absolutePath($path) !== null, 'file exists before delete');
        $outbox = Database::connection()->insert('email_outbox', ['purpose' => 'confirm.application', 'related_type' => 'training_application', 'related_id' => $app['id'], 'recipient' => 'a@example.test', 'subject' => 'TEST-AS confirmation', 'status' => 'queued']);
        $token = csrf_from(Http::get("/admin/applications/{$app['id']}", $jar)->body);
        expect_same(419, Http::post("/admin/applications/{$app['id']}/delete", [], $jar)->status);
        expect_true(TrainingApplication::find($app['id']) !== null);
        $res = Http::post("/admin/applications/{$app['id']}/delete", ['_token' => $token], $jar);
        expect_same(303, $res->status);
        expect_same(null, TrainingApplication::find($app['id']));
        clearstatcache(true); // the test process caches stat/realpath results
        expect_same(null, $store->absolutePath($path), 'file removed');
        expect_same(0, (int) Database::connection()->fetchValue('SELECT COUNT(*) FROM email_outbox WHERE id = :id', ['id' => $outbox]));
        expect_true(Database::connection()->fetchValue("SELECT COUNT(*) FROM admin_audit_logs WHERE action = 'application.deleted' AND entity_id = :id", ['id' => $app['id']]) >= 1);
    } finally {
        as_cleanup();
    }
});

test('applications: under-18 applicants show the guardian consent state', function (): void {
    reset_rate_limits();
    try {
        [$jar] = aa_session('applications_officer');
        $minor = as_application(['age_range' => '15-17', 'is_adult' => 0, 'guardian_name' => 'Guardian Test', 'guardian_phone' => '+233200000001', 'guardian_consent' => 1]);
        $missing = as_application(['age_range' => '15-17', 'is_adult' => 0, 'guardian_consent' => 0]);
        expect_contains('Under 18 — guardian consent given', Http::get("/admin/applications/{$minor['id']}", $jar)->body);
        expect_contains('Under 18 — guardian consent missing', Http::get("/admin/applications/{$missing['id']}", $jar)->body);
        // Possible duplicates: same email + program.
        $d1 = as_application(['email' => 'dupe@example.test']);
        $d2 = as_application(['email' => 'dupe@example.test']);
        $list = Http::get('/admin/applications?q=dupe%40example.test', $jar)->body;
        expect_contains('Possible duplicate', $list);
        expect_contains('Possible duplicate: 1 other application', Http::get("/admin/applications/{$d1['id']}", $jar)->body);
    } finally {
        as_cleanup();
    }
});

test('volunteers, partnerships and contact messages: list, show, status, notes, delete', function (): void {
    reset_rate_limits();
    try {
        [$jar] = aa_session('applications_officer');
        $now = gmdate('Y-m-d H:i:s');
        $cases = [
            '/admin/volunteers' => [VolunteerApplication::class, ['full_name' => 'Test Volunteer', 'email' => 'vol@example.test', 'interest_area' => 'mentoring'], 'Mentoring and coaching trainees', 'contacted', 'volunteer_application'],
            '/admin/partnerships' => [PartnershipEnquiry::class, ['organization_name' => 'Test Org', 'contact_name' => 'Test Person', 'email' => 'org@example.test', 'partnership_type' => 'funding', 'message' => 'Hello'], 'Funding or sponsorship', 'in_discussion', 'partnership_enquiry'],
            '/admin/contacts' => [ContactEnquiry::class, ['full_name' => 'Test Sender', 'email' => 'msg@example.test', 'subject' => 'Hello there', 'message' => '<b>Hi</b>'], 'Hello there', 'replied', 'contact_enquiry'],
        ];
        foreach ($cases as $path => [$model, $data, $expectText, $newStatus, $relatedType]) {
            $ref = as_ref();
            $id = $model::create($data + ['reference' => $ref, 'privacy_accepted_at' => $now]);
            $list = Http::get($path, $jar);
            expect_same(200, $list->status, $path);
            expect_contains($ref, $list->body);
            expect_same(200, Http::get($path . '?status=new&q=' . rawurlencode('zzz-nothing') . '&from=2020-01-01&to=2099-01-01', $jar)->status);
            $injected = Http::get($path . '?q=' . rawurlencode("' OR '1'='1"), $jar);
            expect_same(200, $injected->status);
            expect_not_contains($ref, $injected->body);
            $show = Http::get("{$path}/{$id}", $jar);
            expect_same(200, $show->status, $path);
            expect_contains($expectText, $show->body);
            expect_not_contains('<b>Hi</b>', $show->body);
            if ($path === '/admin/contacts') {
                expect_contains('Reply by email', $show->body);
                expect_contains('mailto:msg@example.test?subject=' . rawurlencode('Re: Hello there [' . $ref . ']'), html_entity_decode($show->body));
            }
            $token = csrf_from($show->body);
            expect_same(419, Http::post("{$path}/{$id}/status", ['status' => $newStatus], $jar)->status);
            expect_same(303, Http::post("{$path}/{$id}/status", ['_token' => $token, 'status' => $newStatus], $jar)->status);
            expect_same($newStatus, $model::find($id)['status']);
            expect_same(303, Http::post("{$path}/{$id}/notes", ['_token' => $token, 'admin_notes' => 'a note'], $jar)->status);
            expect_same('a note', $model::find($id)['admin_notes']);
            expect_true(Database::connection()->fetchValue("SELECT COUNT(*) FROM admin_audit_logs WHERE action = 'submission.status_changed' AND entity_type = :t AND entity_id = :id", ['t' => $relatedType, 'id' => $id]) === 1);
            $box = Database::connection()->insert('email_outbox', ['purpose' => 'x', 'related_type' => $relatedType, 'related_id' => $id, 'recipient' => 'x@example.test', 'subject' => 'TEST-AS related', 'status' => 'queued']);
            expect_same(303, Http::post("{$path}/{$id}/delete", ['_token' => $token], $jar)->status);
            expect_same(null, $model::find($id));
            expect_same(0, (int) Database::connection()->fetchValue('SELECT COUNT(*) FROM email_outbox WHERE id = :id', ['id' => $box]));
            expect_same(404, Http::get("{$path}/{$id}", $jar)->status);
        }
    } finally {
        as_cleanup();
    }
});

test('dashboard: tiles, charts and role-specific content', function (): void {
    reset_rate_limits();
    try {
        [$super] = aa_session('super_admin');
        as_application();
        as_application(['status' => 'accepted']);
        $html = Http::get('/admin', $super)->body;
        foreach (['Welcome back,', 'Recent Applications', 'Programs Overview', 'Application Status', 'Monthly Applications', 'Quick Actions', 'Recent Activity', 'Setup checklist', 'Received in ' . gmdate('Y')] as $needle) {
            expect_contains($needle, $html);
        }
        expect_contains('<svg class="chart chart--donut"', $html);
        expect_contains('<svg class="chart chart--bars"', $html);
        expect_not_contains(' style="', $html, 'no inline styles (CSP)');
        [$officer] = aa_session('applications_officer');
        $o = Http::get('/admin', $officer)->body;
        expect_contains('Recent Applications', $o);
        expect_not_contains('Setup checklist', $o);
        expect_not_contains('Add New Program', $o);
        [$editor] = aa_session('content_editor');
        $e = Http::get('/admin', $editor)->body;
        expect_contains('Add New Program', $e);
        expect_not_contains('Application Status', $e);
        expect_not_contains('Received in', $e);
    } finally {
        as_cleanup();
    }
});
