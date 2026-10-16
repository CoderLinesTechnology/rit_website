<?php

declare(strict_types=1);

namespace App\Models;

final class SeoMeta extends Model
{
    public const TABLE = 'seo_meta';
    public const FILLABLE = ['meta_title', 'meta_description', 'og_image_path', 'updated_by'];

    /** Human names of the pages that have SEO rows. */
    public const PAGES = [
        'home' => 'Homepage', 'about' => 'About Us', 'programs' => 'Programs', 'impact' => 'Our Impact',
        'gallery' => 'Gallery', 'news' => 'News & Stories', 'get-involved' => 'Get Involved', 'volunteer' => 'Volunteer',
        'partner' => 'Partner With Us', 'apply' => 'Apply for Training', 'contact' => 'Contact', 'donate' => 'Donate',
        'privacy' => 'Privacy notice', 'terms' => 'Terms & conditions',
    ];

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $cache = null;

    /** @return array<string, mixed>|null */
    public static function forPage(string $pageKey): ?array
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                foreach (static::db()->fetchAll('SELECT * FROM seo_meta') as $row) {
                    self::$cache[(string) $row['page_key']] = $row;
                }
            } catch (\Throwable) {
                // SEO overrides are optional; pages fall back to their defaults.
            }
        }
        return self::$cache[$pageKey] ?? null;
    }

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return static::db()->fetchAll('SELECT * FROM seo_meta ORDER BY id');
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
