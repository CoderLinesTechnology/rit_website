<?php

declare(strict_types=1);

/*
 * Builds a release ZIP of the project for upload to the host.
 *
 *   php scripts/package-release.php [/path/to/output.zip]
 *
 * Never included: .env files, logs, sessions, cache, private applicant
 * documents, uploaded photos (they belong to the live site, not the code),
 * version-control folders and editor files. Empty storage/uploads folders are
 * kept (with their .htaccess and .gitkeep files) so permissions can be set.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (!class_exists(ZipArchive::class)) {
    fwrite(STDERR, "The zip extension is required.\n");
    exit(1);
}

$root = dirname(__DIR__);
$target = $argv[1] ?? dirname($root) . '/rit-foundation-ghana-' . gmdate('Ymd') . '.zip';

// Paths (relative, with "/" separators) that are never packaged; folder rules end with "/".
$exclude = [
    '#^\.env$#', '#^\.env\.(?!example$)#', '#^\.git/#', '#^\.idea/#', '#^\.vscode/#', '#(^|/)\.DS_Store$#',
    '#^storage/(logs|sessions|cache)/(?!\.gitkeep$|\.htaccess$)#',
    '#^storage/private/applications/(?!\.gitkeep$)#',
    '#^public/uploads/(?!\.gitkeep$|\.htaccess$)#',
    '#^node_modules/#',
];

$zip = new ZipArchive();
if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Cannot write {$target}\n");
    exit(1);
}
$count = 0;
$bytes = 0;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    foreach ($exclude as $pattern) {
        if (preg_match($pattern, $relative) === 1) {
            continue 2;
        }
    }
    $zip->addFile($file->getPathname(), 'rit-foundation-ghana/' . $relative);
    $count++;
    $bytes += $file->getSize();
}
$zip->close();

printf("%s\n%d files, %.1f MB uncompressed, %.1f MB zipped\n", $target, $count, $bytes / 1048576, filesize($target) / 1048576);
