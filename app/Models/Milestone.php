<?php

declare(strict_types=1);

namespace App\Models;

final class Milestone extends Model
{
    public const TABLE = 'milestones';
    public const FILLABLE = ['period_label', 'title', 'description', 'is_verified', 'is_published', 'sort_order'];

    /** @return list<array<string, mixed>> Only verified AND published milestones are shown publicly. */
    public static function published(): array
    {
        return static::db()->fetchAll('SELECT * FROM milestones WHERE is_published = 1 AND is_verified = 1 ORDER BY sort_order, id LIMIT 50');
    }

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return static::db()->fetchAll('SELECT * FROM milestones ORDER BY sort_order, id');
    }
}
