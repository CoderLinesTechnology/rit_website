<?php

declare(strict_types=1);

/*
 * Empties the full-page cache (storage/cache/pages). Saving anything in the
 * admin dashboard already does this; run it after uploading new templates,
 * CSS or code so visitors see the change at once.
 *
 *   php scripts/clear-cache.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

App\Core\PageCache::flush();
echo "Page cache cleared.\n";
