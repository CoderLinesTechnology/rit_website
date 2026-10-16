<?php

declare(strict_types=1);

/*
 * Front controller: every request that is not a real file in public/ is
 * routed here (see public/.htaccess).
 *
 * If your host does not let you point the domain at this public/ folder,
 * see README "Hosting without changing the document root".
 */

// PHP's built-in development server: let it serve existing static files itself.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    if ($file !== __DIR__ . '/' && is_file($file) && !str_ends_with($file, '.php')) {
        return false;
    }
}

define('PUBLIC_ROOT', __DIR__);
$appRoot = getenv('RIT_APP_ROOT') ?: dirname(__DIR__);
require $appRoot . '/app/bootstrap.php';

$app = new App\Core\Application();
$response = $app->handle(App\Core\Request::fromGlobals());
$response->send();
App\Core\Deferred::run();
