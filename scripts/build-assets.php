<?php

declare(strict_types=1);

/*
 * Writes minified copies of the stylesheets (main.min.css, admin.min.css).
 *
 *   php scripts/build-assets.php
 *
 * Run it after editing public/assets/css/*.css. asset('css/main.css') serves
 * the .min.css copy only while it is at least as new as its source, so a
 * forgotten rebuild never serves stale styles — just slightly larger ones.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$dir = dirname(__DIR__) . '/public/assets/css';

function minify_css(string $css): string
{
    // Comments (the stylesheets contain no "/*" inside strings or urls).
    $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
    // Collapse whitespace.
    $css = (string) preg_replace('/\s+/', ' ', $css);
    // Remove spaces around structural characters. ":" is left alone because a
    // space before it is meaningful in selectors (".a :focus" ≠ ".a:focus").
    $css = (string) preg_replace('/\s*([{};,>])\s*/', '$1', $css);
    // The last declaration of a block needs no semicolon.
    $css = str_replace(';}', '}', $css);
    return trim($css) . "\n";
}

foreach (['main', 'admin'] as $name) {
    $source = "{$dir}/{$name}.css";
    if (!is_file($source)) {
        continue;
    }
    $original = (string) file_get_contents($source);
    $minified = minify_css($original);
    $open = substr_count($minified, '{');
    $close = substr_count($minified, '}');
    if ($open !== $close) {
        fwrite(STDERR, "{$name}.css: unbalanced braces after minifying ({$open} vs {$close}); not written.\n");
        exit(1);
    }
    file_put_contents("{$dir}/{$name}.min.css", $minified);
    printf("%-10s %6.1f KB → %6.1f KB (gzip %5.1f KB → %5.1f KB)\n", "{$name}.css",
        strlen($original) / 1024, strlen($minified) / 1024,
        strlen((string) gzencode($original, 9)) / 1024, strlen((string) gzencode($minified, 9)) / 1024);
}
