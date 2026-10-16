<?php

declare(strict_types=1);

use App\Services\DocumentStore;
use App\Services\UploadException;

function ds_pdf(): string
{
    return "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
        . "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
}

test('documents: a real pdf is stored privately with a clean name', function (): void {
    $dir = fx_tmpdir();
    try {
        $store = new DocumentStore($dir . '/private', false);
        $doc = $store->store(fx_file($dir, ds_pdf(), "../../CV of Ama\x07 Mensah.exe.pdf", 'application/pdf'));
        expect_same(1, preg_match('#^\d{4}/\d{2}/[a-f0-9]{32}\.pdf$#', $doc->path));
        expect_same('application/pdf', $doc->mime);
        expect_same('CV of Ama Mensah.exe.pdf', $doc->originalName);
        expect_same(strlen(ds_pdf()), $doc->size);
        $abs = $store->absolutePath($doc->path);
        expect_true($abs !== null && is_file($abs));
        expect_same(ds_pdf(), file_get_contents((string) $abs));
        $store->delete($doc->path);
        expect_same(null, $store->absolutePath($doc->path));
    } finally {
        fx_rrmdir($dir);
    }
});

test('documents: name is forced to the stored type extension and capped', function (): void {
    $dir = fx_tmpdir();
    try {
        $store = new DocumentStore($dir . '/private', false);
        expect_same('document.pdf', $store->store(fx_file($dir, ds_pdf(), '', 'application/pdf'))->originalName);
        expect_same('report.pdf', $store->store(fx_file($dir, ds_pdf(), 'report.php', 'application/pdf'))->originalName === 'report.php.pdf' ? 'report.pdf' : 'x');
        $long = $store->store(fx_file($dir, ds_pdf(), str_repeat('é', 400) . '.pdf'))->originalName;
        expect_true(mb_strlen($long) <= 150, (string) mb_strlen($long));
        expect_true(str_ends_with($long, '.pdf'));
        expect_same('scan.jpg', $store->store(fx_file($dir, fx_image('jpeg'), 'scan.pdf', 'application/pdf'))->originalName);
        expect_same('id.png', $store->store(fx_file($dir, fx_image('png'), 'ID.JPG'))->originalName === 'ID.png' ? 'id.png' : 'x');
    } finally {
        fx_rrmdir($dir);
    }
});

test('documents: jpeg and png are verified, re-encoded and stripped of metadata', function (): void {
    $dir = fx_tmpdir();
    try {
        $store = new DocumentStore($dir . '/private', false);
        $src = function_exists('exif_read_data')
            ? fx_jpeg_with_exif(fx_image('jpeg', 300, 120), 1)
            : fx_image('jpeg', 300, 120);
        $jpg = $store->store(fx_file($dir, $src, 'id.jpeg', 'image/jpeg'));
        expect_same('image/jpeg', $jpg->mime);
        expect_same(1, preg_match('#^\d{4}/\d{2}/[a-f0-9]{32}\.jpg$#', $jpg->path));
        $bytes = (string) file_get_contents((string) $store->absolutePath($jpg->path));
        expect_true(str_starts_with($bytes, "\xFF\xD8"));
        expect_not_contains('Exif', $bytes);
        expect_not_contains('GPS-SECRET-MARKER', $bytes);
        expect_same([300, 120], array_slice((array) getimagesize((string) $store->absolutePath($jpg->path)), 0, 2));
        expect_same(filesize((string) $store->absolutePath($jpg->path)), $jpg->size);

        $png = $store->store(fx_file($dir, fx_image('png', 80, 60, true) . 'trailing-payload', 'scan.png', 'image/png'));
        expect_same('image/png', $png->mime);
        expect_same(1, preg_match('#^\d{4}/\d{2}/[a-f0-9]{32}\.png$#', $png->path));
        $pb = (string) file_get_contents((string) $store->absolutePath($png->path));
        expect_true(str_starts_with($pb, "\x89PNG"));
        expect_not_contains('trailing-payload', $pb);
        expect_same('scan.png', $png->originalName);
    } finally {
        fx_rrmdir($dir);
    }
});

test('documents: fake and disallowed files are refused', function (): void {
    $dir = fx_tmpdir();
    try {
        $store = new DocumentStore($dir . '/private', false);
        $bad = [
            'php as pdf' => fx_file($dir, "<?php echo 1;", 'x.pdf', 'application/pdf'),
            'php as jpg' => fx_file($dir, "<?php echo 1;", 'x.jpg', 'image/jpeg'),
            'text as pdf' => fx_file($dir, "just text\n", 'x.pdf', 'application/pdf'),
            'html' => fx_file($dir, '<html></html>', 'x.pdf', 'application/pdf'),
            'gif' => fx_file($dir, fx_image('gif'), 'x.png', 'image/png'),
            'webp' => (imagetypes() & IMG_WEBP) ? fx_file($dir, fx_image('webp'), 'x.jpg', 'image/jpeg') : fx_file($dir, 'x', 'x.jpg'),
            'zip-ish' => fx_file($dir, "PK\x03\x04" . str_repeat("\0", 50), 'x.pdf', 'application/pdf'),
            'pdf magic later' => fx_file($dir, "junk\n%PDF-1.4\n", 'x.pdf', 'application/pdf'),
            'empty' => fx_file($dir, '', 'x.pdf', 'application/pdf'),
            'truncated png' => fx_file($dir, substr(fx_image('png'), 0, 50), 'x.png', 'image/png'),
        ];
        foreach ($bad as $label => $file) {
            expect_throws(UploadException::class, fn () => $store->store($file), $label);
        }
        expect_same([], glob($dir . '/private/*/*/*') ?: []);

        $ihdr = pack('N', 50000) . pack('N', 50000) . "\x08\x02\x00\x00\x00";
        $huge = "\x89PNG\r\n\x1a\n" . pack('N', 13) . 'IHDR' . $ihdr . pack('N', crc32('IHDR' . $ihdr));
        expect_contains('dimensions', expect_throws(UploadException::class, fn () => $store->store(fx_file($dir, $huge, 'x.png', 'image/png')))->getMessage());

        \App\Core\Config::set('app.uploads.document_max_mb', 1);
        expect_contains('too large', expect_throws(UploadException::class, fn () => $store->store(fx_file($dir, ds_pdf() . str_repeat('a', 1_100_000), 'big.pdf')))->getMessage());
        \App\Core\Config::set('app.uploads.document_max_mb', 5);
    } finally {
        \App\Core\Config::set('app.uploads.document_max_mb', 5);
        fx_rrmdir($dir);
    }
});

test('documents: absolutePath and delete refuse traversal and odd paths', function (): void {
    $dir = fx_tmpdir();
    try {
        $store = new DocumentStore($dir . '/private', false);
        $doc = $store->store(fx_file($dir, ds_pdf(), 'a.pdf'));
        file_put_contents($dir . '/secret.pdf', 'keep');
        foreach (['../secret.pdf', '2026/01/../../../secret.pdf', '/etc/passwd', str_repeat('a', 32) . '.pdf',
            '2026/01/' . str_repeat('A', 32) . '.pdf', '2026/01/' . str_repeat('a', 32) . '.php', '2026/01/' . str_repeat('a', 32) . '.pdf/..', ''] as $p) {
            expect_same(null, $store->absolutePath($p), $p);
            $store->delete($p);
        }
        expect_true(is_file($dir . '/secret.pdf'));
        expect_true($store->absolutePath($doc->path) !== null);
        $store->delete(null);
        $store->delete($doc->path);
        expect_same(null, $store->absolutePath($doc->path));
    } finally {
        fx_rrmdir($dir);
    }
});

test('documents: storage .htaccess files deny access', function (): void {
    foreach (['storage/.htaccess', 'storage/private/.htaccess'] as $f) {
        expect_contains('Require all denied', (string) file_get_contents(APP_ROOT . '/' . $f));
    }
});
