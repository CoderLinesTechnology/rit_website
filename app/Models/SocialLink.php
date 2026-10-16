<?php

declare(strict_types=1);

namespace App\Models;

final class SocialLink extends Model
{
    public const TABLE = 'social_links';
    public const FILLABLE = ['platform', 'label', 'url', 'sort_order', 'is_active'];

    /** Supported platforms => icon name in the sprite. */
    public const PLATFORMS = [
        'facebook' => 'facebook', 'linkedin' => 'linkedin', 'instagram' => 'instagram', 'tiktok' => 'tiktok',
        'x' => 'twitter-x', 'youtube' => 'youtube', 'whatsapp' => 'whatsapp',
    ];

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return static::db()->fetchAll('SELECT * FROM social_links ORDER BY sort_order, id');
    }
}
