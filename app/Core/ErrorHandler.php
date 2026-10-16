<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Turns PHP warnings into exceptions, logs every failure privately and shows
 * visitors a generic error page. Error details are only displayed when
 * APP_DEBUG is on outside production.
 */
final class ErrorHandler
{
    public static function register(): void
    {
        $debug = (bool) config('app.debug');
        error_reporting(E_ALL);
        ini_set('display_errors', $debug && PHP_SAPI !== 'cli' ? '1' : (PHP_SAPI === 'cli' ? '1' : '0'));
        ini_set('log_errors', '1');
        ini_set('error_log', APP_ROOT . '/storage/logs/php-errors.log');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            if (in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
                Logger::warning('Deprecated: ' . $message, ['file' => basename($file), 'line' => $line]);
                return true;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(static function (Throwable $e): void {
            self::report($e);
            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
                exit(1);
            }
            self::renderFatal();
        });

        register_shutdown_function(static function (): void {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                Logger::error('Fatal: ' . $error['message'], ['file' => basename($error['file']), 'line' => $error['line']]);
                if (PHP_SAPI !== 'cli') {
                    self::renderFatal();
                }
            }
        });
    }

    public static function report(Throwable $e): void
    {
        Logger::error($e::class . ': ' . $e->getMessage(), [
            'file' => str_replace(APP_ROOT, '', $e->getFile()),
            'line' => $e->getLine(),
            'trace' => array_slice(array_map(
                static fn (array $f): string => str_replace(APP_ROOT, '', ($f['file'] ?? '?')) . ':' . ($f['line'] ?? '?'),
                $e->getTrace()
            ), 0, 8),
        ]);
    }

    /** Last-resort page that needs nothing but PHP. */
    private static function renderFatal(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: no-store');
        }
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Something went wrong | RIT Foundation Ghana</title></head><body>'
            . '<main><h1>Sorry, something went wrong</h1><p>The page could not be displayed. Please try again in a few minutes.</p>'
            . '<p><a href="/">Return to the homepage</a></p></main></body></html>';
    }
}
