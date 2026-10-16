<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Paginator;
use App\Core\Request;

/**
 * Base for the table gateways. Subclasses declare TABLE and FILLABLE (the
 * only columns create()/update() will write — anything else in the input
 * array is ignored, so a crafted form field can never set e.g. created_by).
 */
abstract class Model
{
    public const TABLE = '';

    /** @var list<string> */
    public const FILLABLE = [];

    protected static function db(): Database
    {
        return Database::connection();
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return static::db()->fetch('SELECT * FROM `' . static::TABLE . '` WHERE id = :id', ['id' => $id]);
    }

    /** @param array<string, mixed> $data */
    public static function create(array $data): int
    {
        return static::db()->insert(static::TABLE, static::only($data));
    }

    /** @param array<string, mixed> $data */
    public static function update(int $id, array $data): void
    {
        static::db()->update(static::TABLE, $id, static::only($data));
    }

    public static function delete(int $id): void
    {
        static::db()->delete(static::TABLE, $id);
    }

    public static function slugExists(string $slug, ?int $exceptId = null): bool
    {
        return static::db()->fetchValue(
            'SELECT 1 FROM `' . static::TABLE . '` WHERE slug = :slug AND id <> :id LIMIT 1',
            ['slug' => $slug, 'id' => $exceptId ?? 0]
        ) !== null;
    }

    /** A unique slug based on $base ("title", "title-2", …). */
    public static function uniqueSlug(string $base, ?int $exceptId = null): string
    {
        $slug = $base;
        for ($i = 2; static::slugExists($slug, $exceptId); $i++) {
            $slug = mb_substr($base, 0, 150) . '-' . $i;
        }
        return $slug;
    }

    /**
     * Runs a paginated listing. $where is a SQL fragment using named
     * placeholders that are all present in $params.
     *
     * @param array<string, mixed> $params
     * @return array{items: list<array<string, mixed>>, paginator: Paginator}
     */
    protected static function paginateQuery(
        Request $request,
        string $select,
        string $from,
        string $where,
        array $params,
        string $orderBy,
        int $perPage,
    ): array {
        $total = (int) static::db()->fetchValue("SELECT COUNT(*) FROM {$from} WHERE {$where}", $params);
        $paginator = Paginator::fromRequest($request, $total, $perPage);
        $items = $total === 0 ? [] : static::db()->fetchAll(
            "SELECT {$select} FROM {$from} WHERE {$where} ORDER BY {$orderBy} LIMIT :__limit OFFSET :__offset",
            $params + ['__limit' => $paginator->perPage, '__offset' => $paginator->offset]
        );
        return ['items' => $items, 'paginator' => $paginator];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected static function only(array $data): array
    {
        return array_intersect_key($data, array_flip(static::FILLABLE));
    }
}
