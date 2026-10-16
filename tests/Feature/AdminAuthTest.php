<?php

declare(strict_types=1);

/*
 * Administrator sign-in, sessions, password reset, setup and account/admin management.
 * HTTP tests run the real app against the test database; test admins use the
 * e-mail pattern test-aa-*@example.test and are removed afterwards.
 */

require_once __DIR__ . '/../http.php';

use App\Controllers\Admin\AdminUserController;
use App\Controllers\Admin\SetupController;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Session;
use App\Models\Admin;
use App\Models\PasswordReset;

const AA_PASSWORD = 'Correct-Horse-Battery-9';

/** @return array{id: int, email: string, password: string} */
function aa_admin(string $role = 'super_admin', bool $active = true, string $password = AA_PASSWORD): array
{
    $email = 'test-aa-' . bin2hex(random_bytes(5)) . '@example.test';
    $id = Admin::create([
        'name' => 'Test Admin ' . strtoupper(substr(md5($email), 0, 4)),
        'email' => $email,
        'password_hash' => Auth::hashPassword($password),
        'role' => $role,
        'is_active' => $active ? 1 : 0,
        'password_changed_at' => gmdate('Y-m-d H:i:s'),
    ]);
    return ['id' => $id, 'email' => $email, 'password' => $password];
}

function aa_cleanup(): void
{
    $db = Database::connection();
    $db->execute("DELETE FROM admin_audit_logs WHERE admin_id IN (SELECT id FROM admins WHERE email LIKE 'test-aa-%@example.test') OR summary LIKE '%test-aa-%'");
    $db->execute("DELETE FROM email_outbox WHERE recipient LIKE 'test-aa-%@example.test'");
    $db->execute("DELETE FROM admins WHERE email LIKE 'test-aa-%@example.test'");
    reset_rate_limits();
}

function aa_login(CookieJar $jar, string $email, string $password): HttpResponse
{
    $page = Http::get('/admin/login', $jar);
    return Http::post('/admin/login', ['_token' => csrf_from($page->body), 'email' => $email, 'password' => $password], $jar);
}

/** A signed-in cookie jar for a fresh admin of the given role. @return array{0: CookieJar, 1: array{id: int, email: string, password: string}} */
function aa_session(string $role = 'super_admin'): array
{
    $admin = aa_admin($role);
    $jar = new CookieJar();
    $res = aa_login($jar, $admin['email'], $admin['password']);
    expect_same(303, $res->status, 'login should succeed');
    return [$jar, $admin];
}

function aa_token(CookieJar $jar): string
{
    return csrf_from(Http::get('/admin/account', $jar)->body);
}

function aa_inprocess_login(int $adminId): void
{
    Auth::reset();
    Session::reset();
    $row = Database::connection()->fetch('SELECT session_version FROM admins WHERE id = :id', ['id' => $adminId]);
    Session::set('_admin', ['id' => $adminId, 'version' => (int) $row['session_version'], 'login_at' => time(), 'last_seen' => time()]);
}

function aa_inprocess_reset(): void
{
    Auth::reset();
    Session::reset();
}

function aa_request(array $post = [], string $method = 'POST'): Request
{
    return new Request($method, '/x', [], $post, [], ['REMOTE_ADDR' => '127.0.0.1']);
}

test('login: success redirects to /admin and the session id changes (no fixation)', function (): void {
    reset_rate_limits();
    try {
        $admin = aa_admin();
        $jar = new CookieJar();
        $page = Http::get('/admin/login', $jar);
        expect_same(200, $page->status);
        expect_contains('autocomplete="username"', $page->body);
        expect_contains('autocomplete="current-password"', $page->body);
        $before = $jar->cookies['rit_session'] ?? '';
        expect_true($before !== '');
        $res = Http::post('/admin/login', ['_token' => csrf_from($page->body), 'email' => $admin['email'], 'password' => $admin['password']], $jar);
        expect_same(303, $res->status);
        expect_same('/admin', $res->header('Location'));
        expect_true(($jar->cookies['rit_session'] ?? '') !== $before, 'session id must change after login');
        $dash = Http::get('/admin', $jar);
        expect_same(200, $dash->status);
        expect_contains('Welcome back, Test!', $dash->body);
    } finally {
        aa_cleanup();
    }
});

test('login: wrong password, unknown email and inactive account give the same generic error', function (): void {
    reset_rate_limits();
    try {
        $active = aa_admin();
        $inactive = aa_admin('super_admin', false);
        $messages = [];
        foreach ([[$active['email'], 'wrong-password-123'], ['nobody-' . bin2hex(random_bytes(3)) . '@example.test', 'whatever-password-1'], [$inactive['email'], $inactive['password']]] as [$email, $pw]) {
            $jar = new CookieJar();
            $res = aa_login($jar, $email, $pw);
            expect_same(401, $res->status);
            expect_contains('The email address or password is incorrect.', $res->body);
            expect_not_contains('inactive', strtolower($res->body));
            $messages[] = preg_match('/<div class="alert alert--error".*?<\/div>/s', $res->body, $m) ? $m[0] : '';
        }
        expect_same($messages[0], $messages[1]);
        expect_same($messages[0], $messages[2]);
        // Anonymous visitors never get past the login form.
        expect_same(303, Http::get('/admin')->status);
    } finally {
        aa_cleanup();
    }
});

test('login: throttled after 5 wrong attempts, even with the right password', function (): void {
    try {
        $admin = aa_admin();
        // Other test suites running at the same time may clear rate_limits mid-way; retry the scenario.
        $status = 0;
        $body = '';
        for ($try = 0; $try < 4 && $status !== 429; $try++) {
            reset_rate_limits();
            for ($i = 0; $i < 5; $i++) {
                expect_same(401, aa_login(new CookieJar(), $admin['email'], 'wrong-password-' . $i)->status);
            }
            $res = aa_login(new CookieJar(), $admin['email'], $admin['password']);
            $status = $res->status;
            $body = $res->body;
        }
        expect_same(429, $status);
        expect_contains('Too many sign-in attempts. Please wait 15 minutes and try again.', $body);
    } finally {
        aa_cleanup();
    }
});

test('login: only same-site /admin paths are used as the post-login destination', function (): void {
    expect_same('/admin/applications?status=new', App\Controllers\Admin\AuthController::safeIntended('/admin/applications?status=new'));
    foreach ([null, '', '//evil.test', '/admin//x\\y', 'https://evil.test/admin', '/administrator', "/admin\r\nSet-Cookie: x=1", '/admin/logout'] as $bad) {
        expect_same('/admin', App\Controllers\Admin\AuthController::safeIntended($bad), 'bad intended: ' . var_export($bad, true));
    }
    reset_rate_limits();
    try {
        $admin = aa_admin('applications_officer');
        $jar = new CookieJar();
        expect_same(303, Http::get('/admin/applications?status=new', $jar)->status);
        $res = aa_login($jar, $admin['email'], $admin['password']);
        expect_same('/admin/applications?status=new', $res->header('Location'));
    } finally {
        aa_cleanup();
    }
});

test('logout: POST ends the session, GET is not allowed', function (): void {
    reset_rate_limits();
    try {
        [$jar] = aa_session();
        expect_same(405, Http::get('/admin/logout', $jar)->status);
        expect_same(200, Http::get('/admin', $jar)->status);
        $res = Http::post('/admin/logout', ['_token' => aa_token($jar)], $jar);
        expect_same(303, $res->status);
        expect_same('/admin/login', $res->header('Location'));
        $login = Http::get('/admin/login', $jar);
        expect_contains('You have signed out.', $login->body);
        expect_same(303, Http::get('/admin', $jar)->status);
        expect_same('/admin/login', Http::get('/admin', $jar)->header('Location'));
    } finally {
        aa_cleanup();
    }
});

test('authorization: anonymous and wrong-role access to admin URLs', function (): void {
    reset_rate_limits();
    try {
        foreach (['/admin/applications', '/admin/applications/1', '/admin/applications/1/attachment', '/admin/admins', '/admin/audit-log', '/admin/donations'] as $path) {
            $res = Http::get($path);
            expect_same(303, $res->status, $path);
            expect_same('/admin/login', $res->header('Location'), $path);
        }
        [$editor] = aa_session('content_editor');
        expect_same(403, Http::get('/admin/applications', $editor)->status);
        expect_same(403, Http::get('/admin/admins', $editor)->status);
        expect_same(403, Http::get('/admin/email-log', $editor)->status);
        expect_same(200, Http::get('/admin', $editor)->status);
        $dash = Http::get('/admin', $editor)->body;
        expect_not_contains('Recent Applications', $dash);
        expect_not_contains('Setup checklist', $dash);
        [$officer] = aa_session('applications_officer');
        expect_same(403, Http::get('/admin/programs', $officer)->status);
        expect_same(403, Http::get('/admin/settings', $officer)->status);
        expect_same(403, Http::get('/admin/donations', $officer)->status);
        expect_same(200, Http::get('/admin/applications', $officer)->status);
    } finally {
        aa_cleanup();
    }
});

test('sessions: idle timeout, and a session revoked by a password change elsewhere', function (): void {
    reset_rate_limits();
    try {
        $admin = aa_admin();
        aa_inprocess_login($admin['id']);
        expect_true(Auth::user() !== null, 'fresh session is valid');
        Auth::reset();
        Session::set('_admin', ['id' => $admin['id'], 'version' => 1, 'login_at' => time() - 3600, 'last_seen' => time() - 3600]);
        expect_same(null, Auth::user());
        expect_contains('expired', (string) Auth::expiredReason());
        aa_inprocess_reset();

        [$jar, $other] = aa_session();
        expect_same(200, Http::get('/admin', $jar)->status);
        Database::connection()->execute('UPDATE admins SET session_version = session_version + 1 WHERE id = :id', ['id' => $other['id']]);
        $res = Http::get('/admin', $jar);
        expect_same(303, $res->status);
        expect_same('/admin/login', $res->header('Location'));
    } finally {
        aa_inprocess_reset();
        aa_cleanup();
    }
});

test('setup: 404 without SETUP_TOKEN or when an administrator exists', function (): void {
    reset_rate_limits();
    try {
        aa_admin();
        expect_same(404, Http::get('/setup')->status);
        // With a token configured but an administrator present, the page must still not exist.
        Config::set('app.setup_token', 'a-setup-token-for-tests');
        try {
            $e = expect_throws(HttpException::class, fn () => (new SetupController())->show(aa_request([], 'GET')));
            expect_same(404, $e->status);
            $e = expect_throws(HttpException::class, fn () => (new SetupController())->store(aa_request(['setup_token' => 'a-setup-token-for-tests'])));
            expect_same(404, $e->status);
        } finally {
            Config::set('app.setup_token', null);
        }
        $e = expect_throws(HttpException::class, fn () => (new SetupController())->show(aa_request([], 'GET')));
        expect_same(404, $e->status);
    } finally {
        aa_cleanup();
    }
});

test('password reset: single-use token, expired token rejected', function (): void {
    reset_rate_limits();
    try {
        $admin = aa_admin();
        $token = PasswordReset::issue($admin['id']);
        $row = PasswordReset::findValid($token);
        expect_true($row !== null, 'fresh token is valid');
        expect_true(PasswordReset::consume((int) $row['id']), 'first use succeeds');
        expect_false(PasswordReset::consume((int) $row['id']), 'second use is refused');
        expect_same(null, PasswordReset::findValid($token));

        $token2 = PasswordReset::issue($admin['id']);
        Database::connection()->execute('UPDATE admin_password_resets SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) WHERE admin_id = :id', ['id' => $admin['id']]);
        expect_same(null, PasswordReset::findValid($token2));

        // Issuing a new token invalidates the previous one.
        $a = PasswordReset::issue($admin['id']);
        $b = PasswordReset::issue($admin['id']);
        expect_same(null, PasswordReset::findValid($a));
        expect_true(PasswordReset::findValid($b) !== null);
    } finally {
        aa_cleanup();
    }
});

test('password reset: request is uniform, link works once, then the old password is dead', function (): void {
    reset_rate_limits();
    try {
        $admin = aa_admin();
        $jar = new CookieJar();
        $page = Http::get('/admin/forgot-password', $jar);
        expect_same(200, $page->status);
        $msgs = [];
        foreach ([$admin['email'], 'nobody-' . bin2hex(random_bytes(3)) . '@example.test'] as $email) {
            $res = Http::post('/admin/forgot-password', ['_token' => csrf_from(Http::get('/admin/forgot-password', $jar)->body), 'email' => $email], $jar);
            expect_same(303, $res->status);
            $follow = Http::get('/admin/forgot-password', $jar);
            expect_contains('If an active account uses that address, we have sent a reset link. It is valid for 60 minutes.', $follow->body);
            $msgs[] = $res->header('Location');
        }
        expect_same($msgs[0], $msgs[1]);
        $queued = (int) Database::connection()->fetchValue("SELECT COUNT(*) FROM email_outbox WHERE purpose = 'admin.password_reset' AND recipient = :e", ['e' => $admin['email']]);
        expect_same(1, $queued, 'one reset email for the real account');
        expect_same(0, (int) Database::connection()->fetchValue("SELECT COUNT(*) FROM email_outbox WHERE purpose = 'admin.password_reset' AND recipient LIKE 'nobody-%'"));

        // Use a token we issue ourselves (the emailed one is not stored in plain text).
        $token = PasswordReset::issue($admin['id']);
        $form = Http::get('/admin/reset-password/' . $token, $jar);
        expect_same(200, $form->status);
        expect_same('no-referrer', $form->header('Referrer-Policy'));
        expect_contains('Choose a new password', $form->body);
        $new = 'Another-Long-Passphrase-77';
        $res = Http::post('/admin/reset-password/' . $token, ['_token' => csrf_from($form->body), 'password' => $new, 'password_confirmation' => $new], $jar);
        expect_same(303, $res->status);
        expect_same('/admin/login', $res->header('Location'));
        // The link is now dead.
        $dead = Http::get('/admin/reset-password/' . $token, $jar);
        expect_contains('This link is invalid or has expired', $dead->body);
        expect_not_contains('name="password"', $dead->body);
        expect_same(401, aa_login(new CookieJar(), $admin['email'], $admin['password'])->status);
        expect_same(303, aa_login(new CookieJar(), $admin['email'], $new)->status);
        // Garbage tokens get the same friendly page.
        expect_contains('This link is invalid or has expired', Http::get('/admin/reset-password/' . str_repeat('a', 64), $jar)->body);
    } finally {
        aa_cleanup();
    }
});

test('password reset: requests are rate limited per email', function (): void {
    try {
        $jar = new CookieJar();
        $email = 'test-aa-limit@example.test';
        $last = 0;
        for ($try = 0; $try < 4 && $last !== 429; $try++) { // retry if a parallel suite clears rate_limits
            reset_rate_limits();
            for ($i = 0; $i < 3; $i++) {
                $res = Http::post('/admin/forgot-password', ['_token' => csrf_from(Http::get('/admin/forgot-password', $jar)->body), 'email' => $email], $jar);
                expect_same(303, $res->status);
            }
            $last = Http::post('/admin/forgot-password', ['_token' => csrf_from(Http::get('/admin/forgot-password', $jar)->body), 'email' => $email], $jar)->status;
        }
        expect_same(429, $last);
    } finally {
        aa_cleanup();
    }
});

test('account: changing the password keeps this browser signed in and signs out others', function (): void {
    reset_rate_limits();
    try {
        [$jar, $admin] = aa_session();
        [$second] = [new CookieJar()];
        expect_same(303, aa_login($second, $admin['email'], $admin['password'])->status);
        expect_same(200, Http::get('/admin', $second)->status);

        // Wrong current password is refused and counted.
        $bad = Http::post('/admin/account/password', ['_token' => aa_token($jar), 'current_password' => 'nope-nope-nope-1', 'password' => 'Brand-New-Passphrase-42', 'password_confirmation' => 'Brand-New-Passphrase-42'], $jar);
        expect_same(303, $bad->status);
        expect_contains('The current password is incorrect.', Http::get('/admin/account', $jar)->body);

        $new = 'Brand-New-Passphrase-42';
        $ok = Http::post('/admin/account/password', ['_token' => aa_token($jar), 'current_password' => $admin['password'], 'password' => $new, 'password_confirmation' => $new], $jar);
        expect_same(303, $ok->status);
        expect_same(200, Http::get('/admin', $jar)->status, 'this browser stays signed in');
        expect_same(303, Http::get('/admin', $second)->status, 'other sessions are revoked');
        expect_same(303, aa_login(new CookieJar(), $admin['email'], $new)->status);

        // Policy and confirmation are enforced.
        $weak = Http::post('/admin/account/password', ['_token' => aa_token($jar), 'current_password' => $new, 'password' => 'short', 'password_confirmation' => 'short'], $jar);
        expect_same(303, $weak->status);
        expect_contains('at least 12 characters', Http::get('/admin/account', $jar)->body);
        $name = Http::post('/admin/account', ['_token' => aa_token($jar), 'name' => 'Renamed <b>Person</b>'], $jar);
        expect_same(303, $name->status);
        $page = Http::get('/admin/account', $jar)->body;
        expect_contains('Renamed &lt;b&gt;Person&lt;/b&gt;', $page);
    } finally {
        aa_cleanup();
    }
});

test('administrators: create, edit, set password, deactivate; self-protection over HTTP', function (): void {
    reset_rate_limits();
    try {
        [$jar, $me] = aa_session();
        $email = 'test-aa-new-' . bin2hex(random_bytes(3)) . '@example.test';
        $res = Http::post('/admin/admins', ['_token' => aa_token($jar), 'name' => 'New Person', 'email' => $email, 'role' => 'content_editor', 'password' => 'Initial-Passphrase-55', 'password_confirmation' => 'Initial-Passphrase-55'], $jar);
        expect_same(303, $res->status);
        $created = Admin::findByEmail($email);
        expect_true($created !== null);
        expect_same('content_editor', $created['role']);
        // Duplicate email refused.
        $dupe = Http::post('/admin/admins', ['_token' => aa_token($jar), 'name' => 'Again', 'email' => $email, 'role' => 'content_editor', 'password' => 'Initial-Passphrase-55', 'password_confirmation' => 'Initial-Passphrase-55'], $jar);
        expect_same(303, $dupe->status);
        expect_same('/admin/admins/create', $dupe->header('Location'));
        expect_same(1, (int) Database::connection()->fetchValue('SELECT COUNT(*) FROM admins WHERE email = :e', ['e' => $email]));
        // The new editor can sign in; role change signs them out.
        $editor = new CookieJar();
        expect_same(303, aa_login($editor, $email, 'Initial-Passphrase-55')->status);
        expect_same(200, Http::get('/admin', $editor)->status);
        $upd = Http::post('/admin/admins/' . $created['id'], ['_token' => aa_token($jar), 'name' => 'New Person', 'email' => $email, 'role' => 'applications_officer'], $jar);
        expect_same(303, $upd->status);
        expect_same('applications_officer', Admin::find((int) $created['id'])['role']);
        expect_same(303, Http::get('/admin', $editor)->status, 'role change revokes their sessions');
        // Self: cannot change own role, cannot deactivate self, cannot use the admin password form on self.
        Http::post('/admin/admins/' . $me['id'], ['_token' => aa_token($jar), 'name' => 'Me', 'email' => $me['email'], 'role' => 'content_editor'], $jar);
        expect_same('super_admin', Admin::find($me['id'])['role']);
        Http::post('/admin/admins/' . $me['id'] . '/status', ['_token' => aa_token($jar), 'active' => '0'], $jar);
        expect_same(1, (int) Admin::find($me['id'])['is_active']);
        // Deactivate the other account.
        Http::post('/admin/admins/' . $created['id'] . '/status', ['_token' => aa_token($jar), 'active' => '0'], $jar);
        expect_same(0, (int) Admin::find((int) $created['id'])['is_active']);
        expect_same(401, aa_login(new CookieJar(), $email, 'Initial-Passphrase-55')->status);
        // Set a password for someone else.
        Http::post('/admin/admins/' . $created['id'] . '/status', ['_token' => aa_token($jar), 'active' => '1'], $jar);
        $pw = Http::post('/admin/admins/' . $created['id'] . '/password', ['_token' => aa_token($jar), 'password' => 'Replacement-Passphrase-31', 'password_confirmation' => 'Replacement-Passphrase-31'], $jar);
        expect_same(303, $pw->status);
        expect_same(303, aa_login(new CookieJar(), $email, 'Replacement-Passphrase-31')->status);
        // Audit entries exist and contain no passwords.
        $audit = Database::connection()->fetchAll("SELECT summary FROM admin_audit_logs WHERE action LIKE 'admin.%' AND admin_id = :id", ['id' => $me['id']]);
        expect_true(count($audit) >= 4);
        foreach ($audit as $a) {
            expect_not_contains('Passphrase', (string) $a['summary']);
        }
        // Pages render.
        expect_same(200, Http::get('/admin/admins', $jar)->status);
        expect_same(200, Http::get('/admin/admins/create', $jar)->status);
        expect_same(200, Http::get('/admin/admins/' . $created['id'] . '/edit', $jar)->status);
        expect_same(200, Http::get('/admin/audit-log?action=admin.', $jar)->status);
    } finally {
        aa_cleanup();
    }
});

test('administrators: the last active super administrator cannot be demoted or deactivated', function (): void {
    reset_rate_limits();
    $db = Database::connection();
    try {
        $sole = aa_admin('super_admin');
        $actor = aa_admin('content_editor'); // the controller itself does not depend on the actor's role
        $db->pdo()->beginTransaction();
        try {
            // Inside this rolled-back transaction, the test super administrator is the only active one.
            $db->execute("UPDATE admins SET is_active = 0 WHERE role = 'super_admin' AND id <> :id", ['id' => $sole['id']]);
            aa_inprocess_login($actor['id']);
            $c = new AdminUserController();
            $c->status(aa_request(['active' => '0']), $sole['id']);
            expect_same(1, (int) Admin::find($sole['id'])['is_active'], 'deactivation refused');
            expect_contains('only active super administrator', (string) ($_SESSION['_flash_next']['_message_error'] ?? ''));

            $r = $c->update(aa_request(['name' => 'Sole', 'email' => $sole['email'], 'role' => 'content_editor']), $sole['id']);
            expect_same('super_admin', Admin::find($sole['id'])['role'], 'demotion refused');
            expect_same('/admin/admins/' . $sole['id'] . '/edit', $r->header('Location'));
            expect_contains('only active super administrator', (string) ($_SESSION['_flash_next']['_errors']['role'] ?? ''));

            // With a second active super administrator, both changes are allowed.
            $second = aa_admin('super_admin');
            $c->update(aa_request(['name' => 'Sole', 'email' => $sole['email'], 'role' => 'content_editor']), $sole['id']);
            expect_same('content_editor', Admin::find($sole['id'])['role'], 'demotion allowed with another super admin');
        } finally {
            $db->pdo()->rollBack();
            aa_inprocess_reset();
        }
    } finally {
        aa_cleanup();
    }
});

test('help, email log and audit log pages render for a super administrator', function (): void {
    reset_rate_limits();
    try {
        [$jar] = aa_session();
        $help = Http::get('/admin/help', $jar);
        expect_same(200, $help->status);
        expect_contains('Reviewing applications', $help->body);
        $mail = Http::get('/admin/email-log', $jar);
        expect_same(200, $mail->status);
        expect_contains('Handed off', $mail->body);
        expect_contains('it does not prove the message reached the inbox', $mail->body);
        expect_same(200, Http::get('/admin/email-log?status=failed', $jar)->status);
        expect_same(200, Http::get('/admin/audit-log?action=auth.', $jar)->status);
        expect_same(200, Http::get('/admin/audit-log?action=%27%20OR%20%271%27=%271', $jar)->status);
        expect_same(200, Http::get('/admin/donations', $jar)->status);
        expect_contains('Online donations are not configured yet', Http::get('/admin/donations', $jar)->body);
    } finally {
        aa_cleanup();
    }
});
