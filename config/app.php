<?php

declare(strict_types=1);

use App\Core\Env;

$env = strtolower(trim(Env::get('APP_ENV', 'production') ?? 'production'));
// Anything unexpected ("Production", "prod", typos) is treated as production: safe defaults.
if (!in_array($env, ['production', 'staging', 'local', 'testing'], true)) {
    $env = 'production';
}

return [
    'name' => 'RIT Foundation Ghana',
    // production | staging | local | testing
    'env' => $env,
    // Never true in production: ErrorHandler refuses to display errors there.
    'debug' => Env::bool('APP_DEBUG', false) && $env !== 'production',
    'url' => rtrim(Env::get('APP_URL', 'http://localhost:8000') ?? '', '/'),
    'key' => Env::secret('APP_KEY'),
    'timezone' => 'Africa/Accra',
    'locale' => 'en-GH',
    // Only a production deployment on its real domain should be indexed.
    'indexable' => $env === 'production' && Env::bool('APP_INDEXABLE', true),
    'force_https' => Env::bool('APP_FORCE_HTTPS', $env === 'production'),
    // Full-page cache for anonymous visitors (see App\Core\PageCache). On by default in
    // production; off for PHP's built-in development server unless PAGE_CACHE=true.
    'page_cache' => Env::bool('PAGE_CACHE', $env === 'production' && PHP_SAPI !== 'cli-server'),

    'session' => [
        'name' => Env::get('SESSION_NAME', 'rit_session'),
        'idle_minutes' => (int) (Env::get('SESSION_IDLE_MINUTES', '30') ?? 30),
        'absolute_hours' => (int) (Env::get('SESSION_ABSOLUTE_HOURS', '8') ?? 8),
    ],

    'uploads' => [
        'image_max_mb' => (int) (Env::get('UPLOAD_IMAGE_MAX_MB', '8') ?? 8),
        'document_max_mb' => (int) (Env::get('UPLOAD_DOCUMENT_MAX_MB', '5') ?? 5),
        // Longest edge of the stored image and of the listing thumbnail.
        'image_max_edge' => 1920,
        'thumb_max_edge' => 720,
        // Refuse anything larger than this before decoding (decompression bombs).
        'image_max_pixels' => 40_000_000,
    ],

    // Optional one-time web setup (see README). Ignored once an administrator exists.
    // Must be at least 32 characters; shorter values are ignored (setup stays disabled).
    'setup_token' => strlen((string) Env::secret('SETUP_TOKEN')) >= 32 ? Env::secret('SETUP_TOKEN') : null,

    // REMOTE_ADDR is used unless the request comes from one of these proxies.
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', Env::get('TRUSTED_PROXIES', '') ?? '')))),

    'retention' => [
        // Used by scripts/purge-old-records.php.
        'submissions_months' => (int) (Env::get('RETENTION_SUBMISSION_MONTHS', '24') ?? 24),
        'audit_months' => (int) (Env::get('RETENTION_AUDIT_MONTHS', '24') ?? 24),
    ],
];
