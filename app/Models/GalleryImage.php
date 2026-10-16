<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Request;

final class GalleryImage extends Model
{
    public const TABLE = 'gallery_images';
    public const FILLABLE = [
        'category_id', 'title', 'image_path', 'thumb_path', 'width', 'height', 'alt_text', 'caption',
        'taken_on', 'is_featured', 'is_published', 'consent_confirmed', 'sort_order', 'uploaded_by',
    ];

    private const PUBLIC_COLUMNS = 'i.id, i.title, i.image_path, i.thumb_path, i.width, i.height, i.alt_text, i.caption, i.taken_on,
        c.name AS category_name, c.slug AS category_slug';

    /**
     * Published photos in published albums; one album when $categoryId is set.
     *
     * @return array{items: list<array<string, mixed>>, paginator: \App\Core\Paginator}
     */
    public static function publishedPaginate(Request $request, ?int $categoryId, int $perPage = 12): array
    {
        $where = 'i.is_published = 1 AND c.is_published = 1';
        $params = [];
        if ($categoryId !== null) {
            $where .= ' AND i.category_id = :cat';
            $params['cat'] = $categoryId;
        }
        return static::paginateQuery(
            $request,
            self::PUBLIC_COLUMNS,
            'gallery_images i JOIN gallery_categories c ON c.id = i.category_id',
            $where,
            $params,
            $categoryId !== null ? 'i.sort_order, i.id' : 'i.id DESC',
            $perPage
        );
    }

    /**
     * Photos for the homepage: featured ones first, then the given album.
     *
     * @return list<array<string, mixed>>
     */
    public static function forHomepage(?int $albumId, int $limit = 8): array
    {
        return static::db()->fetchAll(
            'SELECT ' . self::PUBLIC_COLUMNS . ' FROM gallery_images i JOIN gallery_categories c ON c.id = i.category_id
             WHERE i.is_published = 1 AND c.is_published = 1 AND (i.is_featured = 1 OR i.category_id = :album)
             ORDER BY i.is_featured DESC, i.sort_order, i.id LIMIT :lim',
            ['album' => $albumId ?? 0, 'lim' => $limit]
        );
    }

    /** @return list<array<string, mixed>> All photos of an album (admin). */
    public static function forAlbum(int $categoryId): array
    {
        return static::db()->fetchAll(
            'SELECT * FROM gallery_images WHERE category_id = :cat ORDER BY sort_order, id LIMIT 1000',
            ['cat' => $categoryId]
        );
    }

    public static function nextSortOrder(int $categoryId): int
    {
        return (int) static::db()->fetchValue('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM gallery_images WHERE category_id = :cat', ['cat' => $categoryId]);
    }

    public static function countAll(): int
    {
        return (int) static::db()->fetchValue('SELECT COUNT(*) FROM gallery_images');
    }
}
