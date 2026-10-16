<?php

declare(strict_types=1);

namespace App\Models;

final class TeamMember extends Model
{
    public const TABLE = 'team_members';
    public const FILLABLE = ['full_name', 'role_title', 'bio', 'photo_path', 'photo_thumb_path', 'sort_order', 'is_published', 'consent_confirmed'];

    /** @return list<array<string, mixed>> Published members whose details are approved. */
    public static function published(): array
    {
        return static::db()->fetchAll('SELECT * FROM team_members WHERE is_published = 1 AND consent_confirmed = 1 ORDER BY sort_order, id LIMIT 50');
    }

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return static::db()->fetchAll('SELECT * FROM team_members ORDER BY sort_order, id');
    }
}
