<?php

declare(strict_types=1);

/*
 * Bulk-imports approved photographs from a local folder into a gallery album
 * (e.g. photos downloaded from the Foundation's Google Drive folders).
 *
 *   php scripts/import-gallery.php --album=tech-skills-challenge-season-2 --dir=/path/to/photos
 *        [--alt="Participants at the Tech Skills Challenge Season 2 grand finale"]
 *        [--taken-on=2025-05-24] [--publish --consent-confirmed]
 *
 * Every image is validated, resized and re-encoded exactly like a browser
 * upload (metadata such as GPS location is removed). Imported photos are
 * UNPUBLISHED unless --publish and --consent-confirmed are both given, so an
 * administrator can review descriptions and permissions first.
 * Files that are already imported (same content) are skipped.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\AuditLog;
use App\Core\Database;
use App\Models\GalleryImage;
use App\Services\ImageUploader;
use App\Services\UploadException;

$options = getopt('', ['album:', 'dir:', 'alt::', 'taken-on::', 'publish', 'consent-confirmed']);
$albumSlug = (string) ($options['album'] ?? '');
$dir = rtrim((string) ($options['dir'] ?? ''), '/');
$publish = isset($options['publish']);
$consent = isset($options['consent-confirmed']);
$takenOn = (string) ($options['taken-on'] ?? '');

if ($albumSlug === '' || $dir === '' || !is_dir($dir)) {
    fwrite(STDERR, "Usage: php scripts/import-gallery.php --album=<album-slug> --dir=<folder> [--alt=...] [--taken-on=YYYY-MM-DD] [--publish --consent-confirmed]\n");
    exit(1);
}
if ($publish && !$consent) {
    fwrite(STDERR, "--publish also requires --consent-confirmed (confirm the Foundation may publish these photos of identifiable people).\n");
    exit(1);
}
if ($takenOn !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $takenOn)) {
    fwrite(STDERR, "--taken-on must be YYYY-MM-DD.\n");
    exit(1);
}

$db = Database::connection();
$album = $db->fetch('SELECT id, name FROM gallery_categories WHERE slug = :s', ['s' => $albumSlug]);
if ($album === null) {
    fwrite(STDERR, "No album with slug '{$albumSlug}'. Create it in the admin dashboard first.\n");
    exit(1);
}
$alt = trim((string) ($options['alt'] ?? '')) ?: 'Photo from ' . $album['name'];

$hashFile = APP_ROOT . '/storage/cache/import-hashes-' . (int) $album['id'] . '.txt';
$seen = is_file($hashFile) ? array_flip(file($hashFile, FILE_IGNORE_NEW_LINES) ?: []) : [];

$files = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->isFile() && preg_match('/\.(jpe?g|png|webp)$/i', $file->getFilename())) {
        $files[] = $file->getPathname();
    }
}
sort($files, SORT_NATURAL);

$uploader = new ImageUploader(null, false);
$imported = 0;
$skipped = 0;
$failed = 0;
foreach ($files as $i => $path) {
    $hash = (string) hash_file('sha256', $path);
    if (isset($seen[$hash])) {
        $skipped++;
        continue;
    }
    try {
        $stored = $uploader->store([
            'name' => basename($path), 'type' => '', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => (int) filesize($path),
        ], 'gallery');
        GalleryImage::create([
            'category_id' => (int) $album['id'],
            'title' => null,
            'image_path' => $stored->path,
            'thumb_path' => $stored->thumbPath,
            'width' => $stored->width,
            'height' => $stored->height,
            'alt_text' => mb_substr($alt, 0, 255),
            'caption' => null,
            'taken_on' => $takenOn !== '' ? $takenOn : null,
            'is_featured' => 0,
            'is_published' => $publish ? 1 : 0,
            'consent_confirmed' => $consent ? 1 : 0,
            'sort_order' => GalleryImage::nextSortOrder((int) $album['id']),
            'uploaded_by' => null,
        ]);
        file_put_contents($hashFile, $hash . "\n", FILE_APPEND | LOCK_EX);
        $imported++;
        echo '.';
    } catch (UploadException $e) {
        $failed++;
        echo "\n  Skipped " . basename($path) . ': ' . $e->getMessage() . "\n";
    }
}

App\Core\PageCache::flush();
AuditLog::record('gallery.imported', 'gallery_category', (int) $album['id'], "Imported {$imported} photo(s) from the command line", null);
echo "\nImported {$imported}, already present {$skipped}, failed {$failed}." . ($publish ? '' : ' Photos are unpublished: review them in Admin › Gallery.') . "\n";
