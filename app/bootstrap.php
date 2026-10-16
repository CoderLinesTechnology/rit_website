<?php

declare(strict_types=1);

/*
 * Application bootstrap shared by the web front controller (public/index.php)
 * and the command-line scripts in scripts/.
 */

if (PHP_VERSION_ID < 80200) {
    http_response_code(500);
    exit('RIT Foundation Ghana requires PHP 8.2 or later.');
}

define('APP_ROOT', dirname(__DIR__));
// The web root that holds assets/ and uploads/. public/index.php defines it as its own folder,
// so a host where public_html is separate from the application (deployment layout C) works
// unchanged; command-line scripts there read RIT_PUBLIC_ROOT (e.g. /home/user/public_html).
if (!defined('PUBLIC_ROOT')) {
    define('PUBLIC_ROOT', rtrim((string) (getenv('RIT_PUBLIC_ROOT') ?: APP_ROOT . '/public'), '/'));
}
define('APP_START', microtime(true));

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, 4));
    $file = APP_ROOT . '/app/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require APP_ROOT . '/app/helpers.php';

App\Core\Env::load(APP_ROOT . '/.env');
App\Core\Config::load(APP_ROOT . '/config');

date_default_timezone_set((string) config('app.timezone', 'Africa/Accra'));
mb_internal_encoding('UTF-8');

App\Core\ErrorHandler::register();
