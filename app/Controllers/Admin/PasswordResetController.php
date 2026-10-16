<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\AuditLog;
use App\Core\Database;
use App\Core\Deferred;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Admin;
use App\Models\PasswordReset;
use App\Services\Mail\Outbox;
use App\Services\Notifier;

/** Emailed, single-use, expiring password reset links for administrators. */
final class PasswordResetController
{
    private const SENT = 'If an active account uses that address, we have sent a reset link. It is valid for 60 minutes.';

    public function request(Request $request): Response
    {
        return view('admin/auth/forgot', ['title' => 'Reset your password', 'enabled' => $this->enabled()]);
    }

    public function send(Request $request): Response
    {
        if (!$this->enabled()) {
            return view('admin/auth/forgot', ['title' => 'Reset your password', 'enabled' => false]);
        }
        $email = strtolower(mb_substr($request->input('email'), 0, 190));

        // Limits apply to every request alike (before any account lookup), so they reveal nothing.
        $ipKey = 'pwreset:ip:' . $request->ip();
        $emailKey = 'pwreset:email:' . $email;
        if (RateLimiter::tooManyAttempts($ipKey, 5) || RateLimiter::tooManyAttempts($emailKey, 3)) {
            throw new HttpException(429, 'Too many password reset requests. Please try again in an hour.', ['Retry-After' => '3600']);
        }
        RateLimiter::hit($ipKey, 3600);
        RateLimiter::hit($emailKey, 3600);

        if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
            $admin = Admin::findByEmail($email);
            if ($admin !== null && (int) $admin['is_active'] === 1) {
                $token = PasswordReset::issue((int) $admin['id']);
                (new Notifier())->passwordReset((string) $admin['email'], (string) $admin['name'], absolute_url('/admin/reset-password/' . $token), PasswordReset::VALID_MINUTES);
                Deferred::add(static fn () => Outbox::process(Outbox::queuedThisRequest(), 5));
                AuditLog::record('auth.password_reset_requested', 'admin', (int) $admin['id'], 'Password reset link requested', (int) $admin['id']);
            }
        }
        flash_message('success', self::SENT);
        return Response::redirect('/admin/forgot-password');
    }

    public function showReset(Request $request, string $token): Response
    {
        $row = $this->enabled() ? PasswordReset::findValid($token) : null;
        return $this->page($row !== null ? 'form' : 'invalid', $token);
    }

    public function reset(Request $request, string $token): Response
    {
        $row = $this->enabled() ? PasswordReset::findValid($token) : null;
        if ($row === null) {
            return $this->page('invalid', $token);
        }
        $post = $request->post();
        $password = is_string($post['password'] ?? null) ? $post['password'] : '';
        $confirm = is_string($post['password_confirmation'] ?? null) ? $post['password_confirmation'] : '';

        $error = null;
        if ($password === '') {
            $error = ['password' => 'Password is required.'];
        } elseif (($problem = Auth::passwordProblem($password, (string) $row['email'], (string) $row['name'])) !== null) {
            $error = ['password' => $problem];
        } elseif (!hash_equals($password, $confirm)) {
            $error = ['password_confirmation' => 'The two passwords do not match.'];
        }
        if ($error !== null) {
            return redirect_with_errors('/admin/reset-password/' . $token, $error)->withHeader('Referrer-Policy', 'no-referrer');
        }

        $adminId = (int) $row['admin_id'];
        $done = Database::connection()->transaction(static function () use ($row, $adminId, $password): bool {
            if (!PasswordReset::consume((int) $row['id'])) {
                return false; // another request used this link first
            }
            Admin::setPassword($adminId, $password);
            return true;
        });
        if (!$done) {
            return $this->page('invalid', $token);
        }
        AuditLog::record('auth.password_reset', 'admin', $adminId, 'Password reset with an emailed link', $adminId);
        flash_message('success', 'Your password has been changed. Please sign in with your new password.');
        return Response::redirect('/admin/login')->withHeader('Referrer-Policy', 'no-referrer');
    }

    private function enabled(): bool
    {
        return config('mail.driver') !== 'none';
    }

    private function page(string $state, string $token): Response
    {
        return view('admin/auth/reset', [
            'title' => 'Choose a new password',
            'state' => $this->enabled() ? $state : 'disabled',
            'token' => $token,
        ])->withHeader('Referrer-Policy', 'no-referrer');
    }
}
