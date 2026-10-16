<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Request;
use App\Services\Settings;

final class Program extends Model
{
    public const TABLE = 'programs';
    public const FILLABLE = [
        'category_id', 'icon', 'title', 'slug', 'summary', 'description',
        'featured_image_path', 'featured_thumb_path', 'featured_image_alt',
        'target_audience', 'eligibility', 'duration', 'location',
        'application_opens_on', 'application_closes_on', 'accepts_applications', 'application_instructions',
        'status', 'is_featured', 'sort_order', 'seo_title', 'seo_description', 'created_by', 'updated_by',
    ];
    public const STATUSES = ['draft', 'published', 'archived'];

    /** SQL condition (alias p) for "applications are open today". Ghana time = UTC. */
    private const OPEN_SQL = "p.status = 'published' AND p.accepts_applications = 1
        AND (p.application_opens_on IS NULL OR p.application_opens_on <= UTC_DATE())
        AND (p.application_closes_on IS NULL OR p.application_closes_on >= UTC_DATE())";

    /**
     * Published programs (optionally in one category), with category fields and
     * an applications_open flag (0/1).
     *
     * @return list<array<string, mixed>>
     */
    public static function published(?int $categoryId = null): array
    {
        $where = "p.status = 'published'";
        $params = [];
        if ($categoryId !== null) {
            $where .= ' AND p.category_id = :cat';
            $params['cat'] = $categoryId;
        }
        return static::withOpenFlag(static::db()->fetchAll(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug, (' . self::OPEN_SQL . ') AS applications_open
             FROM programs p LEFT JOIN program_categories c ON c.id = p.category_id
             WHERE ' . $where . ' ORDER BY p.sort_order, p.title',
            $params
        ));
    }

    /** @return list<array<string, mixed>> Featured published programs for the homepage. */
    public static function featured(int $limit = 6): array
    {
        return static::withOpenFlag(static::db()->fetchAll(
            "SELECT p.id, p.title, p.slug, p.summary, p.icon, p.featured_thumb_path, p.featured_image_alt,
                    c.slug AS category_slug, (" . self::OPEN_SQL . ") AS applications_open
             FROM programs p LEFT JOIN program_categories c ON c.id = p.category_id
             WHERE p.status = 'published' AND p.is_featured = 1
             ORDER BY p.sort_order, p.title LIMIT :lim",
            ['lim' => $limit]
        ));
    }

    /** @return array<string, mixed>|null A published program with category fields. */
    public static function findPublishedBySlug(string $slug): ?array
    {
        $rows = static::withOpenFlag(static::db()->fetchAll(
            "SELECT p.*, c.name AS category_name, c.slug AS category_slug, (" . self::OPEN_SQL . ") AS applications_open
             FROM programs p LEFT JOIN program_categories c ON c.id = p.category_id
             WHERE p.slug = :slug AND p.status = 'published' LIMIT 1",
            ['slug' => $slug]
        ));
        return $rows[0] ?? null;
    }

    /**
     * Programs currently accepting applications (for the application form).
     * Empty when applications are switched off site-wide.
     *
     * @return list<array<string, mixed>>
     */
    public static function openForApplications(): array
    {
        if (!Settings::bool('applications.enabled')) {
            return [];
        }
        return static::db()->fetchAll(
            'SELECT p.id, p.title, p.slug, p.application_closes_on FROM programs p WHERE ' . self::OPEN_SQL . ' ORDER BY p.sort_order, p.title'
        );
    }

    /** @return array<string, mixed>|null An open program by id (re-checked at submission time). */
    public static function findOpen(int $id): ?array
    {
        if (!Settings::bool('applications.enabled')) {
            return null;
        }
        return static::db()->fetch('SELECT p.id, p.title, p.slug FROM programs p WHERE p.id = :id AND ' . self::OPEN_SQL, ['id' => $id]);
    }

    public static function anyOpen(): bool
    {
        return static::openForApplications() !== [];
    }

    /** @return list<array<string, mixed>> */
    public static function images(int $programId): array
    {
        return static::db()->fetchAll(
            'SELECT * FROM program_images WHERE program_id = :id ORDER BY sort_order, id',
            ['id' => $programId]
        );
    }

    /** @return array<string, mixed>|null */
    public static function findImage(int $programId, int $imageId): ?array
    {
        return static::db()->fetch(
            'SELECT * FROM program_images WHERE id = :img AND program_id = :id',
            ['img' => $imageId, 'id' => $programId]
        );
    }

    /** @param array{image_path: string, thumb_path: string, width: int, height: int, alt_text: string, caption?: ?string} $image */
    public static function addImage(int $programId, array $image): int
    {
        $next = (int) static::db()->fetchValue('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM program_images WHERE program_id = :id', ['id' => $programId]);
        return static::db()->insert('program_images', [
            'program_id' => $programId,
            'image_path' => $image['image_path'],
            'thumb_path' => $image['thumb_path'],
            'width' => $image['width'],
            'height' => $image['height'],
            'alt_text' => $image['alt_text'],
            'caption' => $image['caption'] ?? null,
            'sort_order' => $next,
        ]);
    }

    public static function deleteImage(int $imageId): void
    {
        static::db()->delete('program_images', $imageId);
    }

    /**
     * Admin listing with optional filters: status, category (id), q (title search).
     *
     * @return array{items: list<array<string, mixed>>, paginator: \App\Core\Paginator}
     */
    public static function adminPaginate(Request $request, int $perPage = 20): array
    {
        $where = '1 = 1';
        $params = [];
        $status = $request->query('status');
        if (in_array($status, self::STATUSES, true)) {
            $where .= ' AND p.status = :status';
            $params['status'] = $status;
        }
        $category = $request->queryInt('category');
        if ($category > 0) {
            $where .= ' AND p.category_id = :cat';
            $params['cat'] = $category;
        }
        $q = mb_substr($request->query('q'), 0, 100);
        if ($q !== '') {
            $where .= ' AND p.title LIKE :q';
            $params['q'] = Database::likeTerm($q);
        }
        $result = static::paginateQuery(
            $request,
            'p.id, p.title, p.slug, p.status, p.is_featured, p.accepts_applications, p.application_opens_on, p.application_closes_on, p.updated_at, c.name AS category_name,
             (SELECT COUNT(*) FROM training_applications a WHERE a.program_id = p.id) AS application_count,
             (' . self::OPEN_SQL . ') AS applications_open',
            'programs p LEFT JOIN program_categories c ON c.id = p.category_id',
            $where,
            $params,
            'p.sort_order, p.title',
            $perPage
        );
        $result['items'] = static::withOpenFlag($result['items']);
        return $result;
    }

    public static function countByStatus(string $status): int
    {
        return (int) static::db()->fetchValue('SELECT COUNT(*) FROM programs WHERE status = :s', ['s' => $status]);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private static function withOpenFlag(array $rows): array
    {
        $enabled = Settings::bool('applications.enabled');
        foreach ($rows as &$row) {
            if (array_key_exists('applications_open', $row)) {
                $row['applications_open'] = $enabled && (int) $row['applications_open'] === 1;
            }
        }
        return $rows;
    }
}
