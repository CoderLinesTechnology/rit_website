<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

/**
 * Site settings: values saved by administrators (site_settings table) on
 * top of the defaults defined in config/settings.php. Loaded with a single
 * query per request.
 */
final class Settings
{
    /** @var array<string, string>|null */
    private static ?array $values = null;

    /** @var list<array{platform: string, label: string, url: string}>|null */
    private static ?array $social = null;

    public static function get(string $key, string $default = ''): string
    {
        $values = self::load();
        if (array_key_exists($key, $values)) {
            return $values[$key];
        }
        $definition = self::definition($key);
        if ($definition !== null) {
            return (string) ($definition['default'] ?? '');
        }
        return $default;
    }

    public static function bool(string $key): bool
    {
        return self::get($key, '0') === '1';
    }

    /** @return list<string> Non-empty lines of a "lines" setting. */
    public static function lines(string $key): array
    {
        $lines = array_map('trim', explode("\n", str_replace("\r", '', self::get($key))));
        return array_values(array_filter($lines, static fn (string $l): bool => $l !== ''));
    }

    /** @return array<string, mixed>|null */
    public static function definition(string $key): ?array
    {
        $fields = (array) config('settings.fields', []);
        $definition = $fields[$key] ?? null;
        return is_array($definition) ? $definition : null;
    }

    /** @return array<string, array<string, mixed>> */
    public static function definitions(): array
    {
        return (array) config('settings.fields', []);
    }

    /** @return array<string, array{label: string, permission: string}> */
    public static function groups(): array
    {
        return (array) config('settings.groups', []);
    }

    /**
     * Saves values (keys must be defined settings) in one transaction.
     *
     * @param array<string, string> $values
     */
    public static function save(array $values, ?int $adminId): void
    {
        $db = Database::connection();
        $db->transaction(static function (Database $db) use ($values, $adminId): void {
            foreach ($values as $key => $value) {
                if (self::definition($key) === null) {
                    throw new \InvalidArgumentException('Unknown setting.');
                }
                $db->execute(
                    'INSERT INTO site_settings (setting_key, setting_value, updated_by) VALUES (:k, :v, :a)
                     ON DUPLICATE KEY UPDATE setting_value = :v2, updated_by = :a2',
                    ['k' => $key, 'v' => $value, 'a' => $adminId, 'v2' => $value, 'a2' => $adminId]
                );
            }
        });
        self::flush();
        \App\Core\PageCache::flush();
    }

    /** @return list<array{platform: string, label: string, url: string}> */
    public static function socialLinks(): array
    {
        if (self::$social !== null) {
            return self::$social;
        }
        try {
            $rows = Database::connection()->fetchAll(
                'SELECT platform, label, url FROM social_links WHERE is_active = 1 ORDER BY sort_order, id'
            );
            self::$social = array_map(static fn (array $r): array => [
                'platform' => (string) $r['platform'],
                'label' => (string) $r['label'],
                'url' => (string) $r['url'],
            ], $rows);
        } catch (\Throwable $e) {
            Logger::error('Could not load social links: ' . $e->getMessage());
            \App\Core\PageCache::markDegraded();
            self::$social = [];
        }
        return self::$social;
    }

    /**
     * Facts flagged 'verify' that are still empty — shown on the dashboard.
     *
     * @return list<string> Labels.
     */
    public static function missingVerifiedFacts(): array
    {
        $missing = [];
        foreach (self::definitions() as $key => $definition) {
            if (!empty($definition['verify']) && trim(self::get($key)) === '') {
                $missing[] = (string) $definition['label'];
            }
        }
        return $missing;
    }

    public static function flush(): void
    {
        self::$values = null;
        self::$social = null;
    }

    /** @return array<string, string> */
    private static function load(): array
    {
        if (self::$values !== null) {
            return self::$values;
        }
        try {
            $rows = Database::connection()->fetchAll('SELECT setting_key, setting_value FROM site_settings');
            $values = [];
            foreach ($rows as $row) {
                $values[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
            }
            self::$values = $values;
        } catch (\Throwable $e) {
            // The page can still render with defaults; the failure is logged once and
            // the page is kept out of the page cache so the fallback is not frozen.
            Logger::error('Could not load site settings: ' . $e->getMessage());
            \App\Core\PageCache::markDegraded();
            self::$values = [];
        }
        return self::$values;
    }
}
