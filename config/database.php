<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'host' => Env::get('DB_HOST', '127.0.0.1'),
    'port' => (int) (Env::get('DB_PORT', '3306') ?? 3306),
    // Optional: a Unix socket path, used instead of host/port when set.
    'socket' => Env::get('DB_SOCKET', ''),
    'name' => Env::get('DB_NAME', ''),
    'user' => Env::get('DB_USER', ''),
    'password' => Env::get('DB_PASS', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];
