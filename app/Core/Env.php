<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal .env reader. Values already present in the real environment
 * (e.g. set by the host or with SetEnv in .htaccess) take precedence.
 */
final class Env
{
    /** @var array<string, string> */
    private static array $values = [];

    /** Values that mean "not configured yet" wherever they appear. */
    private const PLACEHOLDERS = [
        'replace_me', 'replace-me', 'changeme', 'change_me', 'change-me', 'your-api-key',
        'your_secret', 'secret', 'password', 'example', 'todo', 'null', 'none', 'xxx',
    ];

    public static function load(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) {
            return;
        }
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                continue;
            }
            $value = trim($value);
            $quoted = strlen($value) >= 2
                && ($value[0] === '"' || $value[0] === "'")
                && $value[-1] === $value[0];
            if ($quoted) {
                $value = substr($value, 1, -1);
            } elseif (($hash = strpos($value, ' #')) !== false) {
                $value = rtrim(substr($value, 0, $hash));
            }
            self::$values[$key] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $real = getenv($key);
        if ($real !== false && $real !== '') {
            return $real;
        }
        if (isset($_SERVER[$key]) && is_string($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }
        return self::$values[$key] ?? $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null || $value === '') {
            return $default;
        }
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * A secret is only usable when it is non-blank and not an obvious
     * placeholder. Placeholder secrets are treated as unset so that, for
     * example, a webhook signing key of "changeme" can never verify anything.
     */
    public static function secret(string $key): ?string
    {
        $value = trim((string) self::get($key, ''));
        if ($value === '' || self::isPlaceholder($value)) {
            return null;
        }
        return $value;
    }

    public static function isPlaceholder(string $value): bool
    {
        $normalised = strtolower(trim($value));
        if (in_array($normalised, self::PLACEHOLDERS, true)) {
            return true;
        }
        return (bool) preg_match('/^(<.*>|x{3,}|\*{3,}|your[-_].*|replace[-_ ]?me.*|change[-_ ]?me.*)$/', $normalised);
    }
}
