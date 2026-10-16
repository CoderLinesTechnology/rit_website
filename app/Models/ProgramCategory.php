<?php

declare(strict_types=1);

namespace App\Models;

final class ProgramCategory extends Model
{
    public const TABLE = 'program_categories';
    public const FILLABLE = ['name', 'slug', 'description', 'icon', 'image_path', 'thumb_path', 'image_alt', 'sort_order'];

    /**
     * All categories with the number of PUBLISHED programs in each.
     *
     * @return list<array<string, mixed>>
     */
    public static function allWithCounts(bool $publishedOnly = true): array
    {
        // A correlated COUNT per row uses idx(category_id) and avoids GROUP BY over a
        // TEXT column, which forces an on-disk temporary table in MySQL/MariaDB.
        $condition = $publishedOnly ? " AND p.status = 'published'" : '';
        return static::db()->fetchAll(
            "SELECT c.*, (SELECT COUNT(*) FROM programs p WHERE p.category_id = c.id{$condition}) AS program_count
             FROM program_categories c
             ORDER BY c.sort_order, c.name"
        );
    }

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return static::db()->fetchAll('SELECT * FROM program_categories ORDER BY sort_order, name');
    }

    /** @return array<string, mixed>|null */
    public static function findBySlug(string $slug): ?array
    {
        return static::db()->fetch('SELECT * FROM program_categories WHERE slug = :slug', ['slug' => $slug]);
    }

    public static function programCount(int $id): int
    {
        return (int) static::db()->fetchValue('SELECT COUNT(*) FROM programs WHERE category_id = :id', ['id' => $id]);
    }
}
