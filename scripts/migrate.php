<?php

declare(strict_types=1);

/*
 * Applies database/migrations/NNNN_description.sql files that are not yet
 * recorded in schema_migrations, in filename order.
 *
 * The everyday application user only has SELECT/INSERT/UPDATE/DELETE, so run
 * this with a user that may change the schema, e.g.:
 *
 *   DB_USER=cpanel_dbowner DB_PASS='…' php scripts/migrate.php
 *   php scripts/migrate.php --status     (list without applying)
 *
 * Statements in a migration file are separated by a semicolon at the end of a line.
 * Take a database backup first (see README "Backups").
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;

$statusOnly = in_array('--status', $argv, true);
$db = Database::connection();

$applied = array_column($db->fetchAll('SELECT version FROM schema_migrations'), 'version');
$files = glob(APP_ROOT . '/database/migrations/[0-9][0-9][0-9][0-9]_*.sql') ?: [];
sort($files);

$pending = array_values(array_filter($files, static fn (string $f): bool => !in_array(basename($f, '.sql'), $applied, true)));

if ($pending === []) {
    echo "Database is up to date (" . count($applied) . " migration(s) applied).\n";
    exit(0);
}

foreach ($pending as $file) {
    $version = basename($file, '.sql');
    if ($statusOnly) {
        echo "Pending: {$version}\n";
        continue;
    }
    echo "Applying {$version} … ";
    $sql = (string) file_get_contents($file);
    $statements = array_filter(array_map('trim', preg_split('/;\s*$/m', $sql) ?: []), static fn (string $s): bool => $s !== '' && !preg_match('/^(--[^\n]*\n?)+$/', $s));
    try {
        foreach ($statements as $statement) {
            $db->pdo()->exec($statement);
        }
        $db->execute('INSERT INTO schema_migrations (version) VALUES (:v)', ['v' => $version]);
        echo "done\n";
    } catch (Throwable $e) {
        echo "FAILED\n";
        fwrite(STDERR, $e->getMessage() . "\nStopped. Fix the migration (DDL statements are not transactional in MySQL; check what was applied) and run again.\n");
        exit(1);
    }
}
