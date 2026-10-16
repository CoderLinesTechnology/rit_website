<?php

declare(strict_types=1);

namespace App\Models;

final class NewsCategory extends Model
{
    public const TABLE = 'news_categories';
    public const FILLABLE = ['name', 'slug', 'description', 'sort_order'];
    public const SUCCESS_STORIES_SLUG = 'success-stories';

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return static::db()->fetchAll('SELECT * FROM news_categories ORDER BY sort_order, name');
    }

    /** @return list<array<string, mixed>> Categories with their count of visible articles. */
    public static function allWithCounts(): array
    {
        return static::db()->fetchAll(
            "SELECT c.*, (SELECT COUNT(*) FROM news_articles a WHERE a.category_id = c.id AND a.status = 'published' AND a.published_at <= UTC_TIMESTAMP()) AS article_count
             FROM news_categories c ORDER BY c.sort_order, c.name"
        );
    }

    /** @return array<string, mixed>|null */
    public static function findBySlug(string $slug): ?array
    {
        return static::db()->fetch('SELECT * FROM news_categories WHERE slug = :slug', ['slug' => $slug]);
    }

    public static function articleCount(int $id): int
    {
        return (int) static::db()->fetchValue('SELECT COUNT(*) FROM news_articles WHERE category_id = :id', ['id' => $id]);
    }
}
