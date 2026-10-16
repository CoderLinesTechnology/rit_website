<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AuditLog;
use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Admin;

/**
 * One-time creation of the first super administrator through the browser.
 * Exists only while SETUP_TOKEN is configured AND no administrator exists;
 * otherwise every request is an ordinary 404 (the page is not revealed).
 */
final class SetupController
{
    public function show(Request $request): Response
    {
        $this->assertAvailable();
        return view('admin/auth/setup', ['title' => 'First-time setup']);
    }

    public function store(Request $request): Response
    {
        $this->assertAvailable();
        $ipKey = 'setup:ip:' . $request->ip();
        if (RateLimiter::tooManyAttempts($ipKey, 5)) {
            throw new HttpException(429, 'Too many attempts. Please try again in an hour.', ['Retry-After' => '3600']);
        }
        RateLimiter::hit($ipKey, 3600);

        $post = $request->post();
        unset($post['setup_token']); // never flashed back into the form
        $given = $request->input('setup_token');
        if (!hash_equals((string) config('app.setup_token'), $given)) {
            AuditLog::record('auth.setup_failed', null, null, 'Wrong setup token');
            return redirect_with_errors('/setup', ['setup_token' => 'The setup token is not correct.'], $post);
        }

        $v = Validator::make($post, [
            'name' => 'required|line|max:120',
            'email' => 'required|email|max:190',
        ], ['name' => 'Name', 'email' => 'Email address']);
        $password = is_string($post['password'] ?? null) ? $post['password'] : '';
        $confirm = is_string($post['password_confirmation'] ?? null) ? $post['password_confirmation'] : '';
        if ($password === '') {
            $v->addError('password', 'Password is required.');
        } elseif ($v->passes() && ($problem = Auth::passwordProblem($password, (string) $v->validated()['email'], (string) $v->validated()['name'])) !== null) {
            $v->addError('password', $problem);
        } elseif (!hash_equals($password, $confirm)) {
            $v->addError('password_confirmation', 'The two passwords do not match.');
        }
        if ($v->fails()) {
            return redirect_with_errors('/setup', $v->errors(), $post);
        }
        $data = $v->validated();

        $db = Database::connection();
        $admin = $db->transaction(static function (Database $db) use ($data, $password): ?array {
            if ((int) $db->fetchValue('SELECT COUNT(*) FROM admins FOR UPDATE') !== 0) {
                return null;
            }
            $id = Admin::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password_hash' => Auth::hashPassword($password),
                'role' => 'super_admin',
                'is_active' => 1,
                'password_changed_at' => gmdate('Y-m-d H:i:s'),
            ]);
            return $db->fetch('SELECT id, session_version FROM admins WHERE id = :id', ['id' => $id]);
        });
        if ($admin === null) {
            throw HttpException::notFound();
        }
        AuditLog::record('admin.created', 'admin', (int) $admin['id'], 'First super administrator created through setup', (int) $admin['id']);
        Auth::login($admin);
        flash_message('success', 'Setup complete. Remove SETUP_TOKEN from your .env file now.');
        return Response::redirect('/admin');
    }

    private function assertAvailable(): void
    {
        if (config('app.setup_token') === null || Admin::count() !== 0) {
            throw HttpException::notFound();
        }
    }
}
