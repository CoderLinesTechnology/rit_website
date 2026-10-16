<?php

declare(strict_types=1);

// Prints a new random APP_KEY value. Paste it into .env as APP_KEY=...
// Changing the key later only resets rate-limit counters; no data is encrypted with it.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

echo bin2hex(random_bytes(32)), PHP_EOL;
