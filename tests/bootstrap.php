<?php

declare(strict_types=1);

// Tests always run against the separate test database defined in .env.testing.
$testingEnv = dirname(__DIR__) . '/.env.testing';
if (is_file($testingEnv)) {
    foreach (file($testingEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line !== '' && $line[0] !== '#' && str_contains($line, '=')) {
            putenv($line);
        }
    }
}
putenv('APP_ENV=testing');

require dirname(__DIR__) . '/app/bootstrap.php';
