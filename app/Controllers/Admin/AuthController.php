<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/** Sign-in and sign-out. Failures never reveal whether an account exists. */
final class AuthController
{
    public const GENERIC_ERROR = 'The email address or password is incorrect.';
    public const THROTTLED = 'Too many sign-in attempts. Please wait 15 minutes and try again.';

    public function showLogin(Request $request): Response
    {
        return $this->form('', null);
    }

    public function login(Request $request): Response
    {
        $email = mb_substr($request->input('email'), 0, 190);
        // Passwords are used exactly as typed (never trimmed).
        $raw = $request->post()['password'] ?? '';
        $password = is_string($raw) ? $raw : '';

        $result = Auth::attempt($email, $password, $request);
        if ($result === 'throttled') {
            return $this->form('', self::THROTTLED, 429);
        }
        if ($result !== 'ok') {
            return $this->form($email, self::GENERIC_ERROR, 401);
        }

        $intended = Session::get('_intended');
        Session::forget('_intended');
        return Response::redirect(self::safeIntended(is_string($intended) ? $intended : null));
    }

    public function logout(Request $request): Response
    {
        Auth::logout();
        flash_message('success', 'You have signed out.');
        return Response::redirect('/admin/login');
    }

    /** Only same-site admin paths are honoured as a post-login destination. */
    public static function safeIntended(?string $path): string
    {
        if ($path === null || !str_starts_with($path, '/admin') || str_starts_with($path, '//')
            || preg_match('/[\r\n\\\\]/', $path) === 1 || str_starts_with($path, '/admin/logout')
            || (strlen($path) > 6 && $path[6] !== '/' && $path[6] !== '?')) {
            return '/admin';
        }
        return $path;
    }

    private function form(string $email, ?string $error, int $status = 200): Response
    {
        return view('admin/auth/login', [
            'title' => 'Sign in',
            'email' => $email,
            'error' => $error,
            'canReset' => config('mail.driver') !== 'none',
            'sessionNote' => flash('info') === null ? Auth::expiredReason() : null,
        ], $status);
    }
}
