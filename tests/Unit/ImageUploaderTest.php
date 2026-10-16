<?php

declare(strict_types=1);

use App\Services\ImageUploader;
use App\Services\UploadException;

function fx_tmpdir(): string
{
    $dir = sys_get_temp_dir() . '/rit-test-' . bin2hex(random_bytes(6));
    mkdir($dir, 0755, true);
    return $dir;
}

function fx_rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        $f->isDir() && !$f->isLink() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($dir);
}

/** Real image bytes made with GD. */
function fx_image(string $type, int $w = 400, int $h = 200, bool $alpha = false): string
{
    $im = imagecreatetruecolor($w, $h);
    if ($alpha) {
        imagealphablending($im, false);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, (int) imagecolorallocatealpha($im, 10, 200, 30, 80));
    } else {
        imagefill($im, 0, 0, (int) imagecolorallocate($im, 200, 40, 40));
        imagefilledrectangle($im, 0, 0, intdiv($w, 2), intdiv($h, 2), (int) imagecolorallocate($im, 20, 20, 240));
    }
    ob_start();
    match ($type) {
        'jpeg' => imagejpeg($im, null, 90),
        'png' => imagepng($im),
        'webp' => imagewebp($im, null, 80),
        'gif' => imagegif($im),
    };
    return (string) ob_get_clean();
}

/** Inserts an EXIF APP1 segment (orientation + a fake GPS-ish comment string) after the JPEG SOI. */
function fx_jpeg_with_exif(string $jpeg, int $orientation): string
{
    $tiff = "MM\x00\x2A\x00\x00\x00\x08" . "\x00\x01" . "\x01\x12\x00\x03\x00\x00\x00\x01" . pack('n', $orientation) . "\x00\x00" . "\x00\x00\x00\x00";
    $payload = "Exif\x00\x00" . $tiff . 'GPS-SECRET-MARKER';
    return substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload . substr($jpeg, 2);
}

/** @return array{name: string, type: string, tmp_name: string, error: int, size: int} */
function fx_file(string $dir, string $bytes, string $name, string $clientType = 'application/octet-stream'): array
{
    $path = $dir . '/upload-' . bin2hex(random_bytes(4)) . '.tmp';
    file_put_contents($path, $bytes);
    return ['name' => $name, 'type' => $clientType, 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => strlen($bytes)];
}

function fx_uploader(string $root): ImageUploader
{
    return new ImageUploader($root . '/public', false);
}

test('image: stores jpeg, png and webp as re-encoded files with a thumbnail', function (): void {
    $dir = fx_tmpdir();
    try {
        $up = fx_uploader($dir);
        $webp = (imagetypes() & IMG_WEBP) === IMG_WEBP;
        foreach (['jpeg' => 'a.jpg', 'png' => 'b.png'] + ($webp ? ['webp' => 'c.webp'] : []) as $type => $name) {
            $out = $up->store(fx_file($dir, fx_image($type), $name), 'gallery');
            expect_same(1, preg_match('#^uploads/gallery/\d{4}/\d{2}/[a-f0-9]{32}\.(webp|jpg)$#', $out->path), $out->path);
            expect_same(1, preg_match('#^uploads/gallery/\d{4}/\d{2}/[a-f0-9]{32}-thumb\.(webp|jpg)$#', $out->thumbPath));
            expect_true(is_file($dir . '/public/' . $out->path));
            expect_true(is_file($dir . '/public/' . $out->thumbPath));
            expect_same(400, $out->width);
            expect_same(200, $out->height);
            $bytes = (string) file_get_contents($dir . '/public/' . $out->path);
            if ($webp) {
                expect_true(str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP', 'not a WebP file');
            } else {
                expect_true(str_starts_with($bytes, "\xFF\xD8"), 'not a JPEG file');
            }
            expect_same('0644', substr(sprintf('%o', fileperms($dir . '/public/' . $out->path)), -4));
        }
        expect_same([], glob($dir . '/public/uploads/gallery/*/*/.tmp-*') ?: []);
    } finally {
        fx_rrmdir($dir);
    }
});

test('image: never upscales, resizes to the max edge and thumbnails to 720', function (): void {
    $dir = fx_tmpdir();
    try {
        $up = fx_uploader($dir);
        $big = $up->store(fx_file($dir, fx_image('jpeg', 3000, 1500), 'big.jpg'), 'news');
        expect_same(1920, $big->width);
        expect_same(960, $big->height);
        $thumb = getimagesize($dir . '/public/' . $big->thumbPath);
        expect_true($thumb !== false && max($thumb[0], $thumb[1]) <= 720 && max($thumb[0], $thumb[1]) >= 700, 'thumb size');
        $small = $up->store(fx_file($dir, fx_image('png', 300, 100), 'small.png'), 'news');
        expect_same(300, $small->width);
        expect_same(100, $small->height);
    } finally {
        fx_rrmdir($dir);
    }
});

test('image: metadata (EXIF/GPS) is stripped and orientation is applied', function () {
    if (!function_exists('exif_read_data')) {
        return 'skip';
    }
    $dir = fx_tmpdir();
    try {
        $src = fx_jpeg_with_exif(fx_image('jpeg', 400, 200), 6);
        expect_contains('GPS-SECRET-MARKER', $src);
        $out = fx_uploader($dir)->store(fx_file($dir, $src, 'photo.jpg', 'image/jpeg'), 'team');
        foreach ([$out->path, $out->thumbPath] as $p) {
            $bytes = (string) file_get_contents($dir . '/public/' . $p);
            expect_not_contains('Exif', $bytes);
            expect_not_contains('GPS-SECRET-MARKER', $bytes);
        }
        // Orientation 6 means "rotate 90 degrees clockwise": 400x200 becomes 200x400.
        expect_same(200, $out->width);
        expect_same(400, $out->height);
    } finally {
        fx_rrmdir($dir);
    }
});

test('image: png transparency is preserved when output is webp', function () {
    if ((imagetypes() & IMG_WEBP) !== IMG_WEBP) {
        return 'skip';
    }
    $dir = fx_tmpdir();
    try {
        $out = fx_uploader($dir)->store(fx_file($dir, fx_image('png', 100, 100, true), 'a.png'), 'site');
        $im = imagecreatefromwebp($dir . '/public/' . $out->path);
        $alpha = (imagecolorat($im, 50, 50) >> 24) & 0x7F;
        expect_true($alpha > 20, "alpha lost ({$alpha})");
    } finally {
        fx_rrmdir($dir);
    }
});

test('image: jpeg fallback flattens alpha on white', function () {
    if ((imagetypes() & IMG_WEBP) === IMG_WEBP) {
        return 'skip';
    }
    $dir = fx_tmpdir();
    try {
        $out = fx_uploader($dir)->store(fx_file($dir, fx_image('png', 100, 100, true), 'a.png'), 'site');
        expect_true(str_ends_with($out->path, '.jpg'));
        $im = imagecreatefromjpeg($dir . '/public/' . $out->path);
        $rgb = imagecolorat($im, 50, 50);
        expect_true((($rgb >> 8) & 0xFF) > 120, 'not flattened on white');
    } finally {
        fx_rrmdir($dir);
    }
});

test('image: hostile files are refused and nothing is written', function (): void {
    $dir = fx_tmpdir();
    try {
        $up = fx_uploader($dir);
        $png = fx_image('png');
        $hostile = [
            'php renamed jpg' => fx_file($dir, "<?php echo 'pwn'; ?>", 'shell.jpg', 'image/jpeg'),
            'gif polyglot' => fx_file($dir, "GIF89a<?php system(\$_GET['c']); ?>", 'x.jpg', 'image/jpeg'),
            'real gif' => fx_file($dir, fx_image('gif'), 'x.gif', 'image/gif'),
            'text as jpeg' => fx_file($dir, str_repeat("hello world\n", 50), 'a.jpg', 'image/jpeg'),
            'svg' => fx_file($dir, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'a.svg', 'image/svg+xml'),
            'html' => fx_file($dir, '<html><script>1</script></html>', 'a.png', 'image/png'),
            'truncated png' => fx_file($dir, substr($png, 0, 60), 'a.png', 'image/png'),
            'empty' => fx_file($dir, '', 'a.png', 'image/png'),
        ];
        foreach ($hostile as $label => $file) {
            expect_throws(UploadException::class, fn () => $up->store($file, 'gallery'), $label);
        }
        $left = [];
        if (is_dir($dir . '/public/uploads')) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir . '/public/uploads', FilesystemIterator::SKIP_DOTS)) as $f) {
                $left[] = $f->getPathname();
            }
        }
        expect_same([], $left, 'files left behind');
    } finally {
        fx_rrmdir($dir);
    }
});

test('image: jpeg with appended php payload is re-encoded without it', function (): void {
    $dir = fx_tmpdir();
    try {
        $src = fx_image('jpeg') . "<?php system('id'); ?>";
        $out = fx_uploader($dir)->store(fx_file($dir, $src, 'a.jpg'), 'gallery');
        expect_not_contains('<?php', (string) file_get_contents($dir . '/public/' . $out->path));
    } finally {
        fx_rrmdir($dir);
    }
});

test('image: oversized dimensions header is refused', function (): void {
    $dir = fx_tmpdir();
    try {
        $ihdr = pack('N', 50000) . pack('N', 50000) . "\x08\x02\x00\x00\x00";
        $chunk = pack('N', 13) . 'IHDR' . $ihdr . pack('N', crc32('IHDR' . $ihdr));
        $png = "\x89PNG\r\n\x1a\n" . $chunk;
        $e = expect_throws(UploadException::class, fn () => fx_uploader($dir)->store(fx_file($dir, $png, 'a.png', 'image/png'), 'gallery'));
        expect_contains('dimensions', $e->getMessage());
    } finally {
        fx_rrmdir($dir);
    }
});

test('image: size limit, folder allowlist and upload errors', function (): void {
    $dir = fx_tmpdir();
    try {
        $up = fx_uploader($dir);
        \App\Core\Config::set('app.uploads.image_max_mb', 1);
        $big = fx_image('png', 1200, 1200);
        // Noise-free PNG compresses; pad the file past the limit instead.
        $e = expect_throws(UploadException::class, fn () => $up->store(fx_file($dir, $big . str_repeat("\0", 1_100_000), 'a.png'), 'gallery'));
        expect_contains('too large', $e->getMessage());
        \App\Core\Config::set('app.uploads.image_max_mb', 8);

        expect_throws(UploadException::class, fn () => $up->store(fx_file($dir, fx_image('png'), 'a.png'), '../etc'));
        expect_throws(UploadException::class, fn () => $up->store(fx_file($dir, fx_image('png'), 'a.png'), 'uploads'));

        $f = fx_file($dir, fx_image('png'), 'a.png');
        $f['error'] = UPLOAD_ERR_INI_SIZE;
        $e = expect_throws(UploadException::class, fn () => $up->store($f, 'gallery'));
        expect_contains('too large', $e->getMessage());
        $f['error'] = UPLOAD_ERR_PARTIAL;
        expect_contains('partly', expect_throws(UploadException::class, fn () => $up->store($f, 'gallery'))->getMessage());
        $f['error'] = UPLOAD_ERR_NO_TMP_DIR;
        expect_contains('temporary', expect_throws(UploadException::class, fn () => $up->store($f, 'gallery'))->getMessage());

        // With uploaded-file checking on, a plain file is not an uploaded file.
        $strict = new ImageUploader($dir . '/public', true);
        expect_throws(UploadException::class, fn () => $strict->store(fx_file($dir, fx_image('png'), 'a.png'), 'gallery'));
    } finally {
        \App\Core\Config::set('app.uploads.image_max_mb', 8);
        fx_rrmdir($dir);
    }
});

test('image: delete removes own files and refuses traversal and foreign paths', function (): void {
    $dir = fx_tmpdir();
    try {
        $up = fx_uploader($dir);
        $out = $up->store(fx_file($dir, fx_image('png'), 'a.png'), 'programs');
        file_put_contents($dir . '/secret.txt', 'keep');
        file_put_contents($dir . '/public/secret.webp', 'keep');
        mkdir($dir . '/public/uploads/other/2026/01', 0755, true);
        $foreign = 'uploads/other/2026/01/' . str_repeat('a', 32) . '.webp';
        file_put_contents($dir . '/public/' . $foreign, 'keep');

        foreach (['../secret.txt', 'uploads/../../secret.txt', 'uploads/programs/../../../secret.txt', '/etc/passwd',
            'secret.webp', $foreign, 'uploads/programs/2026/01/../../../../secret.txt', "uploads/programs/2026/01/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.webp\0.txt", ''] as $bad) {
            $up->delete($bad);
        }
        $up->delete(null);
        expect_true(is_file($dir . '/secret.txt') && is_file($dir . '/public/secret.webp') && is_file($dir . '/public/' . $foreign));

        expect_true(is_file($dir . '/public/' . $out->path));
        $up->deleteImage($out->path, $out->thumbPath);
        expect_false(is_file($dir . '/public/' . $out->path));
        expect_false(is_file($dir . '/public/' . $out->thumbPath));
    } finally {
        fx_rrmdir($dir);
    }
});

test('image: delete does not follow a symlink out of uploads', function () {
    $dir = fx_tmpdir();
    try {
        $up = fx_uploader($dir);
        $outside = fx_tmpdir();
        file_put_contents($outside . '/' . str_repeat('b', 32) . '.webp', 'keep');
        mkdir($dir . '/public/uploads/gallery', 0755, true);
        if (!@symlink($outside, $dir . '/public/uploads/gallery/2026')) {
            fx_rrmdir($outside);
            return 'skip';
        }
        mkdir($outside . '/01');
        file_put_contents($outside . '/01/' . str_repeat('b', 32) . '.webp', 'keep');
        $up->delete('uploads/gallery/2026/01/' . str_repeat('b', 32) . '.webp');
        expect_true(is_file($outside . '/01/' . str_repeat('b', 32) . '.webp'), 'symlink target was deleted');
        unlink($dir . '/public/uploads/gallery/2026');
        fx_rrmdir($outside);
    } finally {
        fx_rrmdir($dir);
    }
});
