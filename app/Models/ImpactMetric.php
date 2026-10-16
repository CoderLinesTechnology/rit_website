<?php

declare(strict_types=1);

namespace App\Models;

final class ImpactMetric extends Model
{
    public const TABLE = 'impact_metrics';
    public const FILLABLE = [
        'label', 'value', 'is_approximate', 'description', 'icon', 'reporting_period',
        'image_path', 'thumb_path', 'image_alt', 'status', 'show_on_homepage', 'sort_order', 'created_by', 'updated_by',
    ];
    public const STATUSES = ['draft', 'published'];

    /** @return list<array<string, mixed>> */
    public static function published(bool $homepageOnly = false): array
    {
        $extra = $homepageOnly ? ' AND show_on_homepage = 1' : '';
        return static::db()->fetchAll("SELECT * FROM impact_metrics WHERE status = 'published'{$extra} ORDER BY sort_order, id LIMIT 12");
    }

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return static::db()->fetchAll('SELECT * FROM impact_metrics ORDER BY sort_order, id');
    }

    /** The published headline reach figure (e.g. "576"), or null. */
    public static function headline(): ?array
    {
        return static::db()->fetch("SELECT * FROM impact_metrics WHERE status = 'published' ORDER BY sort_order, id LIMIT 1");
    }
}
