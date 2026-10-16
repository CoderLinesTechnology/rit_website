<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Private PDF / JPG / PNG attachments, stored outside the web root and only ever served
 * through an authenticated controller using absolutePath().
 */
final class DocumentStore
{
    private const PATH_PATTERN = '#^\d{4}/\d{2}/[a-f0-9]{32}\.(?:pdf|jpg|png)$#';

    private readonly string $root;

    public function __construct(?string $root = null, private readonly bool $requireUploadedFile = true)
    {
        $this->root = rtrim($root ?? (APP_ROOT . '/storage/private/applications'), '/\\');
    }

    /**
     * @param array{name?: mixed, type?: mixed, tmp_name?: mixed, error?: mixed, size?: mixed} $file
     * @throws UploadException
     */
    public function store(array $file): StoredDocument
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if (!is_int($error) || $error !== UPLOAD_ERR_OK) {
            throw UploadException::fromErrorCode(is_int($error) ? $error : UPLOAD_ERR_NO_FILE);
        }
        $tmp = $file['tmp_name'] ?? '';
        if (!is_string($tmp) || $tmp === '' || str_contains($tmp, "\0")) {
            throw new UploadException('No file was uploaded.');
        }
        if ($this->requireUploadedFile && !is_uploaded_file($tmp)) {
            throw new UploadException('The file was not uploaded correctly.');
        }
        if (!is_file($tmp) || !is_readable($tmp)) {
            throw new UploadException('The uploaded file could not be read.');
        }
        $size = filesize($tmp);
        $maxMb = max(1, (int) config('app.uploads.document_max_mb', 5));
        if ($size === false || $size === 0) {
            throw new UploadException('The uploaded file is empty.');
        }
        if ($size > $maxMb * 1024 * 1024) {
            throw new UploadException("The document is too large (maximum {$maxMb} MB).");
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!is_string($mime) || !in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
            throw new UploadException('Only PDF, JPG or PNG files are allowed.');
        }
        if ($mime === 'application/pdf') {
            $head = (string) file_get_contents($tmp, false, null, 0, 5);
            if ($head !== '%PDF-') {
                throw new UploadException('Only PDF, JPG or PNG files are allowed.');
            }
            $ext = 'pdf';
        } else {
            $ext = $mime === 'image/png' ? 'png' : 'jpg';
            $this->assertImage($tmp, $mime);
        }

        $sub = gmdate('Y/m');
        $dir = $this->root . '/' . $sub;
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new UploadException('The server could not save the document. Please contact the site administrator.');
        }
        $relative = $sub . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
        $target = $this->root . '/' . $relative;
        $partial = $dir . '/.tmp-' . bin2hex(random_bytes(8));
        try {
            if ($ext === 'pdf') {
                $moved = $this->requireUploadedFile ? move_uploaded_file($tmp, $partial) : copy($tmp, $partial);
                if (!$moved) {
                    throw new UploadException('The server could not save the document. Please contact the site administrator.');
                }
            } else {
                $this->reencode($tmp, $mime, $partial);
            }
            @chmod($partial, 0640);
            if (!@rename($partial, $target)) {
                throw new UploadException('The server could not save the document. Please contact the site administrator.');
            }
            $size = (int) filesize($target);
        } finally {
            if (is_file($partial)) {
                @unlink($partial);
            }
        }

        return new StoredDocument($relative, self::cleanName($file['name'] ?? '', $ext), $size, $mime);
    }

    /** Absolute path of a stored document, or null for anything that is not a valid stored path. */
    public function absolutePath(string $relative): ?string
    {
        if (preg_match(self::PATH_PATTERN, $relative) !== 1) {
            return null;
        }
        $root = realpath($this->root);
        $real = realpath($this->root . '/' . $relative);
        if ($root === false || $real === false || !str_starts_with($real, $root . DIRECTORY_SEPARATOR) || !is_file($real)) {
            return null;
        }
        return $real;
    }

    public function delete(?string $relative): void
    {
        if ($relative === null || $relative === '') {
            return;
        }
        $path = $this->absolutePath($relative);
        if ($path !== null) {
            @unlink($path);
        }
    }

    private function assertImage(string $tmp, string $mime): void
    {
        $info = @getimagesize($tmp);
        $expected = $mime === 'image/png' ? IMAGETYPE_PNG : IMAGETYPE_JPEG;
        if ($info === false || $info[2] !== $expected || $info[0] < 1 || $info[1] < 1) {
            throw new UploadException('The file is not a valid image.');
        }
        ImageUploader::assertDimensions((int) $info[0], (int) $info[1]);
    }

    /** Decodes and re-encodes at the original size, which drops EXIF/GPS and any appended payload. */
    private function reencode(string $tmp, string $mime, string $out): void
    {
        try {
            $image = ImageUploader::decodeFile($tmp, $mime);
            if ($mime === 'image/jpeg') {
                $image = ImageUploader::orient($image, $tmp);
                $ok = imagejpeg($image, $out, 85);
            } else {
                imagealphablending($image, false);
                imagesavealpha($image, true);
                $ok = imagepng($image, $out, 6);
            }
        } catch (UploadException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new UploadException('The image could not be processed.', 0, $e);
        } finally {
            $image = null;
        }
        if (!$ok || !is_file($out) || filesize($out) === 0) {
            throw new UploadException('The image could not be saved.');
        }
    }

    private static function cleanName(mixed $name, string $ext): string
    {
        $name = is_string($name) ? $name : '';
        if (!mb_check_encoding($name, 'UTF-8')) {
            $name = mb_convert_encoding($name, 'UTF-8', 'UTF-8');
        }
        $name = str_replace('\\', '/', $name);
        $name = basename($name);
        $name = (string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $name);
        $name = (string) preg_replace('/\.(?:pdf|jpe?g|png)$/i', '', $name);
        $name = trim($name, ". \t");
        $name = mb_substr($name, 0, 146);
        return ($name === '' ? 'document' : $name) . '.' . $ext;
    }
}
