<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Private application log in storage/logs (outside the web root).
 * Never pass passwords, tokens or message bodies in $context.
 */
final class Logger
{
    private static ?string $requestId = null;

    /** @param array<string, mixed> $context */
    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function requestId(): string
    {
        return self::$requestId ??= bin2hex(random_bytes(6));
    }

    /** @param array<string, mixed> $context */
    private static function write(string $level, string $message, array $context): void
    {
        $dir = APP_ROOT . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        // Strip line breaks so a logged value cannot forge extra log entries.
        $message = str_replace(["\r", "\n"], ' ', $message);
        $line = sprintf(
            "[%s] %s %s %s%s\n",
            gmdate('Y-m-d\TH:i:s\Z'),
            $level,
            self::requestId(),
            $message,
            $context === [] ? '' : ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR)
        );
        $file = $dir . '/app-' . gmdate('Y-m-d') . '.log';
        $new = !is_file($file);
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
        if ($new) {
            @chmod($file, 0640);
        }
    }
}
