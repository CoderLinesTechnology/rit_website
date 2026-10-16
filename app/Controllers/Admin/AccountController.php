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

/** "My account": change own name and password. */
final class AccountController extends AdminController
{
    public function edit(Request $request): Response
    {
        $me = Admin::find((int) Auth::id());
        return $this->page('admin/account', 'My account', 'account', ['me' => $this->findOr404($me)]);
    }

    public function update(Request $request): Response
    {
        $v = Validator::make($request->post(), ['name' => 'required|line|max:120'], ['name' => 'Name']);
        if ($v->fails()) {
            return redirect_with_errors('/admin/account', $v->errors(), $request->post());
        }
        $id = (int) Auth::id();
        Admin::update($id, ['name' => $v->validated()['name']]);
        AuditLog::record('account.updated', 'admin', $id, 'Changed own display name');
        flash_message('success', 'Your name has been updated.');
        return redirect('/admin/account');
    }

    public function password(Request $request): Response
    {
        $id = (int) Auth::id();
        $me = $this->findOr404(Admin::find($id));
        $post = $request->post();
        $current = is_string($post['current_password'] ?? null) ? $post['current_password'] : '';
        $new = is_string($post['password'] ?? null) ? $post['password'] : '';
        $confirm = is_string($post['password_confirmation'] ?? null) ? $post['password_confirmation'] : '';

        // Wrong current passwords count toward the same limit as sign-in attempts.
        $key = 'login:account:' . strtolower((string) $me['email']) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, Auth::MAX_ATTEMPTS_PER_ACCOUNT)) {
            throw new HttpException(429, 'Too many attempts. Please wait 15 minutes and try again.', ['Retry-After' => (string) Auth::LOCKOUT_SECONDS]);
        }
        $hash = Admin::passwordHash($id);
        if ($hash === null || $current === '' || !password_verify($current, $hash)) {
            RateLimiter::hit($key, Auth::LOCKOUT_SECONDS);
            AuditLog::record('auth.password_check_failed', 'admin', $id, 'Wrong current password while changing password');
            return redirect_with_errors('/admin/account', ['current_password' => 'The current password is incorrect.']);
        }
        $problem = $new === '' ? 'Password is required.' : Auth::passwordProblem($new, (string) $me['email'], (string) $me['name']);
        if ($problem !== null) {
            return redirect_with_errors('/admin/account', ['password' => $problem]);
        }
        if (!hash_equals($new, $confirm)) {
            return redirect_with_errors('/admin/account', ['password_confirmation' => 'The two passwords do not match.']);
        }

        RateLimiter::clear($key);
        Admin::setPassword($id, $new); // ends every session, including this one
        $fresh = Database::connection()->fetch('SELECT id, session_version FROM admins WHERE id = :id', ['id' => $id]);
        Auth::login($fresh ?? throw HttpException::notFound());
        AuditLog::record('account.password_changed', 'admin', $id, 'Changed own password; other sessions signed out');
        flash_message('success', 'Your password has been changed. Any other devices have been signed out.');
        return redirect('/admin/account');
    }
}
