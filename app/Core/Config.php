<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Loads every config/*.php file into a dot-addressable array:
 * config('app.name') reads key "name" from config/app.php.
 */
final class Config
{
    /** @var array<string, mixed> */
    private static array $items = [];

    public static function load(string $directory): void
    {
        foreach (glob($directory . '/*.php') ?: [] as $file) {
            $values = require $file;
            if (is_array($values)) {
                self::$items[basename($file, '.php')] = $values;
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    /** For tests only. */
    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $ref = &self::$items;
        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }
        $ref = $value;
    }
}
