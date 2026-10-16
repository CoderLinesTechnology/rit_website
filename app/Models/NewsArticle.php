<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Request;

final class NewsArticle extends Model
{
    public const TABLE = 'news_articles';
    public const FILLABLE = [
        'category_id', 'title', 'slug', 'excerpt', 'content',
        'featured_image_path', 'featured_thumb_path', 'featured_image_alt',
        'author_name', 'status', 'published_at', 'is_featured', 'consent_confirmed',
        'seo_title', 'seo_description', 'created_by', 'updated_by',
    ];
    public const STATUSES = ['draft', 'published'];

    /** Visible to the public: published and not scheduled in the future. */
    private const VISIBLE = "a.status = 'published' AND a.published_at IS NOT NULL AND a.published_at <= UTC_TIMESTAMP()";

    private const LIST_COLUMNS = 'a.id, a.title, a.slug, a.excerpt, a.published_at, a.featured_image_path, a.featured_thumb_path,
        a.featured_image_alt, a.author_name, a.category_id, c.name AS category_name, c.slug AS category_slug';

    /**
     * Public listing, optionally filtered by category and a search term.
     *
     * @return array{items: list<array<string, mixed>>, paginator: \App\Core\Paginator}
     */
    public static function publishedPaginate(Request $request, ?int $categoryId, string $search = '', int $perPage = 9, ?int $excludeCategoryId = null): array
    {
        $where = self::VISIBLE;
        $params = [];
        if ($categoryId !== null) {
            $where .= ' AND a.category_id = :cat';
            $params['cat'] = $categoryId;
        }
        if ($excludeCategoryId !== null) {
            $where .= ' AND (a.category_id IS NULL OR a.category_id <> :excat)';
            $params['excat'] = $excludeCategoryId;
        }
        $search = mb_substr(trim($search), 0, 100);
        if ($search !== '') {
            $where .= ' AND (a.title LIKE :q1 OR a.excerpt LIKE :q2)';
            $params['q1'] = Database::likeTerm($search);
            $params['q2'] = Database::likeTerm($search);
        }
        return static::paginateQuery(
            $request,
            self::LIST_COLUMNS,
            'news_articles a LEFT JOIN news_categories c ON c.id = a.category_id',
            $where,
            $params,
            'a.published_at DESC, a.id DESC',
            $perPage
        );
    }

    /** @return list<array<string, mixed>> */
    public static function latest(int $limit = 3, ?int $categoryId = null, ?int $excludeId = null): array
    {
        $where = self::VISIBLE;
        $params = ['lim' => $limit];
        if ($categoryId !== null) {
            $where .= ' AND a.category_id = :cat';
            $params['cat'] = $categoryId;
        }
        if ($excludeId !== null) {
            $where .= ' AND a.id <> :ex';
            $params['ex'] = $excludeId;
        }
        return static::db()->fetchAll(
            'SELECT ' . self::LIST_COLUMNS . ' FROM news_articles a LEFT JOIN news_categories c ON c.id = a.category_id
             WHERE ' . $where . ' ORDER BY a.published_at DESC, a.id DESC LIMIT :lim',
            $params
        );
    }

    /** @return array<string, mixed>|null */
    public static function findPublishedBySlug(string $slug): ?array
    {
        return static::db()->fetch(
            'SELECT a.*, c.name AS category_name, c.slug AS category_slug
             FROM news_articles a LEFT JOIN news_categories c ON c.id = a.category_id
             WHERE a.slug = :slug AND ' . self::VISIBLE,
            ['slug' => $slug]
        );
    }

    /**
     * The latest published success story whose participant consent is confirmed
     * (used for the quote card on the Impact page).
     *
     * @return array<string, mixed>|null
     */
    public static function latestConsentedSuccessStory(): ?array
    {
        return static::db()->fetch(
            'SELECT ' . self::LIST_COLUMNS . ' FROM news_articles a JOIN news_categories c ON c.id = a.category_id
             WHERE c.slug = :slug AND a.consent_confirmed = 1 AND ' . self::VISIBLE . '
             ORDER BY a.published_at DESC LIMIT 1',
            ['slug' => NewsCategory::SUCCESS_STORIES_SLUG]
        );
    }

    /** @return list<array{slug: string, updated_at: string}> For the sitemap. */
    public static function sitemapEntries(): array
    {
        return static::db()->fetchAll('SELECT a.slug, a.updated_at FROM news_articles a WHERE ' . self::VISIBLE . ' ORDER BY a.published_at DESC LIMIT 5000');
    }

    /**
     * Admin listing; filters: status, category (id), q.
     *
     * @return array{items: list<array<string, mixed>>, paginator: \App\Core\Paginator}
     */
    public static function adminPaginate(Request $request, int $perPage = 20): array
    {
        $where = '1 = 1';
        $params = [];
        $status = $request->query('status');
        if (in_array($status, self::STATUSES, true)) {
            $where .= ' AND a.status = :status';
            $params['status'] = $status;
        }
        $category = $request->queryInt('category');
        if ($category > 0) {
            $where .= ' AND a.category_id = :cat';
            $params['cat'] = $category;
        }
        $q = mb_substr($request->query('q'), 0, 100);
        if ($q !== '') {
            $where .= ' AND a.title LIKE :q';
            $params['q'] = Database::likeTerm($q);
        }
        return static::paginateQuery(
            $request,
            'a.id, a.title, a.slug, a.status, a.published_at, a.is_featured, a.consent_confirmed, a.updated_at, c.name AS category_name, c.slug AS category_slug',
            'news_articles a LEFT JOIN news_categories c ON c.id = a.category_id',
            $where,
            $params,
            'COALESCE(a.published_at, a.created_at) DESC, a.id DESC',
            $perPage
        );
    }

    public static function countPublished(): int
    {
        return (int) static::db()->fetchValue('SELECT COUNT(*) FROM news_articles a WHERE ' . self::VISIBLE);
    }
}
