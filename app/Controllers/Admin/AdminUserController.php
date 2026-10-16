<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AuditLog;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Admin;

/** Administrator accounts (super administrators only; enforced by the route). */
final class AdminUserController extends AdminController
{
    public const ROLE_DESCRIPTIONS = [
        'super_admin' => 'Full access: content, applications, donations, settings, administrator accounts and logs.',
        'content_editor' => 'Programs, news and stories, gallery, impact figures, team and website text. No access to applicants.',
        'applications_officer' => 'Training, volunteer, partnership and contact submissions only. No access to website content.',
    ];

    public function index(Request $request): Response
    {
        return $this->page('admin/admins/index', 'Administrators', 'admins', ['admins' => Admin::all(), 'meId' => (int) Auth::id()]);
    }

    public function create(Request $request): Response
    {
        return $this->form(null);
    }

    public function store(Request $request): Response
    {
        $post = $request->post();
        $v = $this->validate($post, null);
        if ($v->fails()) {
            return redirect_with_errors('/admin/admins/create', $v->errors(), $post);
        }
        $d = $v->validated();
        try {
            $id = Admin::create([
                'name' => $d['name'],
                'email' => $d['email'],
                'password_hash' => Auth::hashPassword((string) $post['password']),
                'role' => $d['role'],
                'is_active' => 1,
                'password_changed_at' => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (\PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            return redirect_with_errors('/admin/admins/create', ['email' => 'Another administrator already uses that email address.'], $post);
        }
        AuditLog::record('admin.created', 'admin', $id, 'Created an administrator account (' . Auth::ROLES[$d['role']] . ')');
        flash_message('success', 'Administrator created. Share the initial password securely (not by email) and ask them to change it in My account.');
        return redirect('/admin/admins');
    }

    public function edit(Request $request, int $id): Response
    {
        return $this->form($this->findOr404(Admin::find($id)));
    }

    public function update(Request $request, int $id): Response
    {
        $target = $this->findOr404(Admin::find($id));
        $isSelf = $id === (int) Auth::id();
        $post = $request->post();
        if ($isSelf) {
            $post['role'] = (string) $target['role']; // nobody changes their own role
        }
        $v = $this->validate($post, $id, false);
        if ($v->fails()) {
            return redirect_with_errors('/admin/admins/' . $id . '/edit', $v->errors(), $post);
        }
        $d = $v->validated();
        $roleChanged = $d['role'] !== $target['role'];
        $emailChanged = strtolower((string) $d['email']) !== strtolower((string) $target['email']);
        // Changing your own sign-in email needs your current password (a stolen session alone is not enough).
        if ($isSelf && $emailChanged) {
            $current = is_string($post['current_password'] ?? null) ? $post['current_password'] : '';
            $hash = Admin::passwordHash($id);
            if ($current === '' || $hash === null || !password_verify($current, $hash)) {
                return redirect_with_errors('/admin/admins/' . $id . '/edit', ['current_password' => 'Enter your current password to change your email address.'], $post);
            }
        }

        $outcome = Database::connection()->transaction(static function () use ($id, $target, $d, $roleChanged, $emailChanged, $isSelf): string {
            if ($roleChanged && $target['role'] === 'super_admin' && (int) $target['is_active'] === 1) {
                Admin::lockSuperAdmins();
                if (Admin::otherActiveSuperAdmins($id) < 1) {
                    return 'last_super';
                }
            }
            Admin::update($id, ['name' => $d['name'], 'email' => $d['email'], 'role' => $d['role']]);
            if ($emailChanged) {
                // Reset links sent to the old address stop working.
                Database::connection()->execute('UPDATE admin_password_resets SET used_at = UTC_TIMESTAMP() WHERE admin_id = :id AND used_at IS NULL', ['id' => $id]);
            }
            if ($roleChanged || ($emailChanged && !$isSelf)) {
                Admin::revokeSessions($id);
            }
            return 'ok';
        });
        if ($outcome === 'last_super') {
            return redirect_with_errors('/admin/admins/' . $id . '/edit', ['role' => 'This is the only active super administrator. Make someone else a super administrator first.'], $post);
        }
        AuditLog::record('admin.updated', 'admin', $id, 'Updated an administrator account'
            . ($roleChanged ? ' (role: ' . Auth::ROLES[$target['role']] . ' → ' . Auth::ROLES[$d['role']] . ')' : '')
            . ($emailChanged ? ' (email changed)' : ''));
        $signedOut = $roleChanged || ($emailChanged && !$isSelf);
        flash_message('success', 'Administrator updated.' . ($signedOut ? ' They have been signed out everywhere.' : ''));
        return redirect('/admin/admins');
    }

    public function password(Request $request, int $id): Response
    {
        $target = $this->findOr404(Admin::find($id));
        if ($id === (int) Auth::id()) {
            flash_message('error', 'Use My account to change your own password.');
            return redirect('/admin/admins/' . $id . '/edit');
        }
        $post = $request->post();
        $password = is_string($post['password'] ?? null) ? $post['password'] : '';
        $confirm = is_string($post['password_confirmation'] ?? null) ? $post['password_confirmation'] : '';
        $problem = $password === '' ? 'Password is required.' : Auth::passwordProblem($password, (string) $target['email'], (string) $target['name']);
        if ($problem !== null) {
            return redirect_with_errors('/admin/admins/' . $id . '/edit', ['password' => $problem]);
        }
        if (!hash_equals($password, $confirm)) {
            return redirect_with_errors('/admin/admins/' . $id . '/edit', ['password_confirmation' => 'The two passwords do not match.']);
        }
        Admin::setPassword($id, $password);
        AuditLog::record('admin.password_set', 'admin', $id, 'Set a new password for an administrator');
        flash_message('success', 'Password set. They have been signed out everywhere. Share it securely.');
        return redirect('/admin/admins');
    }

    public function status(Request $request, int $id): Response
    {
        $target = $this->findOr404(Admin::find($id));
        $activate = $request->input('active') === '1';
        if (!$activate && $id === (int) Auth::id()) {
            flash_message('error', 'You cannot deactivate your own account.');
            return redirect('/admin/admins');
        }
        $outcome = Database::connection()->transaction(static function () use ($id, $target, $activate): string {
            if (!$activate && $target['role'] === 'super_admin' && (int) $target['is_active'] === 1) {
                Admin::lockSuperAdmins();
                if (Admin::otherActiveSuperAdmins($id) < 1) {
                    return 'last_super';
                }
            }
            Admin::update($id, ['is_active' => $activate ? 1 : 0]);
            if (!$activate) {
                Admin::revokeSessions($id);
            }
            return 'ok';
        });
        if ($outcome === 'last_super') {
            flash_message('error', 'This is the only active super administrator and cannot be deactivated.');
            return redirect('/admin/admins');
        }
        AuditLog::record($activate ? 'admin.activated' : 'admin.deactivated', 'admin', $id, ($activate ? 'Activated' : 'Deactivated') . ' an administrator account');
        flash_message('success', $activate ? 'Account activated.' : 'Account deactivated and signed out everywhere.');
        return redirect('/admin/admins');
    }

    /** @param array<string, mixed>|null $admin */
    private function form(?array $admin): Response
    {
        return $this->page('admin/admins/form', $admin === null ? 'Add administrator' : 'Edit administrator', 'admins', [
            'admin' => $admin,
            'roles' => Auth::ROLES,
            'descriptions' => self::ROLE_DESCRIPTIONS,
            'isSelf' => $admin !== null && (int) $admin['id'] === (int) Auth::id(),
        ]);
    }

    /**
     * @param array<string, mixed> $post
     */
    private function validate(array $post, ?int $exceptId, bool $withPassword = true): Validator
    {
        $v = Validator::make($post, [
            'name' => 'required|line|max:120',
            'email' => 'required|email|max:190',
            'role' => 'required|in:' . implode(',', array_keys(Auth::ROLES)),
        ], ['name' => 'Name', 'email' => 'Email address', 'role' => 'Role']);
        if ($v->passes() && Admin::emailTaken((string) $v->validated()['email'], $exceptId)) {
            $v->addError('email', 'Another administrator already uses that email address.');
        }
        if ($withPassword) {
            $password = is_string($post['password'] ?? null) ? $post['password'] : '';
            $confirm = is_string($post['password_confirmation'] ?? null) ? $post['password_confirmation'] : '';
            if ($password === '') {
                $v->addError('password', 'Password is required.');
            } elseif ($v->passes() && ($problem = Auth::passwordProblem($password, (string) $v->validated()['email'], (string) $v->validated()['name'])) !== null) {
                $v->addError('password', $problem);
            } elseif (!hash_equals($password, $confirm)) {
                $v->addError('password_confirmation', 'The two passwords do not match.');
            }
        }
        return $v;
    }
}
