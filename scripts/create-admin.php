<?php

declare(strict_types=1);

/*
 * Creates an administrator account from the command line.
 *
 *   php scripts/create-admin.php
 *   php scripts/create-admin.php --name="Jane Doe" --email=jane@example.org --role=super_admin
 *
 * The password is always typed at the prompt (hidden) or piped on STDIN —
 * never passed as an argument, so it cannot end up in the shell history.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\AuditLog;
use App\Core\Auth;
use App\Core\Database;
use App\Models\Admin;

$options = getopt('', ['name:', 'email:', 'role:']);

function prompt(string $label, bool $hidden = false): string
{
    fwrite(STDOUT, $label);
    $tty = function_exists('posix_isatty') && posix_isatty(STDIN);
    if ($hidden && $tty) {
        shell_exec('stty -echo');
    }
    $value = fgets(STDIN);
    if ($hidden && $tty) {
        shell_exec('stty echo');
        fwrite(STDOUT, PHP_EOL);
    }
    return trim((string) $value);
}

try {
    Database::connection();
} catch (Throwable $e) {
    fwrite(STDERR, "Cannot connect to the database. Check DB_* in .env.\n");
    exit(1);
}

$name = trim((string) ($options['name'] ?? '')) ?: prompt('Full name: ');
$email = strtolower(trim((string) ($options['email'] ?? '')) ?: prompt('Email address: '));
$role = (string) ($options['role'] ?? 'super_admin');

if ($name === '' || mb_strlen($name) > 120) {
    fwrite(STDERR, "A name of up to 120 characters is required.\n");
    exit(1);
}
if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 190) {
    fwrite(STDERR, "A valid email address is required.\n");
    exit(1);
}
if (!array_key_exists($role, Auth::ROLES)) {
    fwrite(STDERR, 'Role must be one of: ' . implode(', ', array_keys(Auth::ROLES)) . "\n");
    exit(1);
}
if (Admin::emailTaken($email)) {
    fwrite(STDERR, "An administrator with that email address already exists.\n");
    exit(1);
}

$password = prompt('Password (min. ' . Auth::PASSWORD_MIN . ' characters, hidden): ', true);
$problem = Auth::passwordProblem($password, $email, $name);
if ($problem !== null) {
    fwrite(STDERR, $problem . "\n");
    exit(1);
}
$confirm = prompt('Repeat the password: ', true);
if (!hash_equals($password, $confirm)) {
    fwrite(STDERR, "The passwords do not match.\n");
    exit(1);
}

$id = Admin::create([
    'name' => $name,
    'email' => $email,
    'password_hash' => Auth::hashPassword($password),
    'role' => $role,
    'is_active' => 1,
    'password_changed_at' => gmdate('Y-m-d H:i:s'),
]);
AuditLog::record('admin.created', 'admin', $id, 'Administrator created from the command line (' . $role . ')', null);

fwrite(STDOUT, "Administrator #{$id} created ({$role}). Sign in at " . config('app.url') . "/admin/login\n");
