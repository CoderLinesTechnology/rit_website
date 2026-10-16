<?php

declare(strict_types=1);

namespace App\Models;

final class GalleryCategory extends Model
{
    public const TABLE = 'gallery_categories';
    public const FILLABLE = ['name', 'slug', 'description', 'event_date', 'external_url', 'is_published', 'sort_order'];
    public const TECH_SKILLS_SLUG = 'tech-skills-challenge-season-2';

    /** @return list<array<string, mixed>> Published albums with their count of published photos. */
    public static function published(): array
    {
        return static::db()->fetchAll(
            'SELECT c.*, (SELECT COUNT(*) FROM gallery_images i WHERE i.category_id = c.id AND i.is_published = 1) AS image_count
             FROM gallery_categories c WHERE c.is_published = 1 ORDER BY c.sort_order, c.name'
        );
    }

    /** @return list<array<string, mixed>> All albums (admin) with photo counts. */
    public static function allWithCounts(): array
    {
        return static::db()->fetchAll(
            'SELECT c.*, (SELECT COUNT(*) FROM gallery_images i WHERE i.category_id = c.id) AS image_count
             FROM gallery_categories c ORDER BY c.sort_order, c.name'
        );
    }

    /** @return array<string, mixed>|null */
    public static function findPublishedBySlug(string $slug): ?array
    {
        return static::db()->fetch('SELECT * FROM gallery_categories WHERE slug = :slug AND is_published = 1', ['slug' => $slug]);
    }

    public static function imageCount(int $id): int
    {
        return (int) static::db()->fetchValue('SELECT COUNT(*) FROM gallery_images WHERE category_id = :id', ['id' => $id]);
    }
}
