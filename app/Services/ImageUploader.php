<?php

declare(strict_types=1);

namespace App\Services;

use GdImage;

/**
 * Validates, decodes, re-encodes and stores an uploaded raster image.
 *
 * The bytes are never trusted: the real type comes from finfo and
 * getimagesize(), the image is fully decoded with GD and written back out as
 * WebP (or JPEG where GD lacks WebP), which drops any embedded payload and
 * all metadata (EXIF, GPS, ICC, comments).
 */
final class ImageUploader
{
    public const FOLDERS = ['gallery', 'programs', 'news', 'impact', 'team', 'site', 'seo'];

    private const MAX_EDGE_ABSOLUTE = 12000;
    private const MIME_BY_TYPE = [
        IMAGETYPE_JPEG => 'image/jpeg',
        IMAGETYPE_PNG => 'image/png',
        IMAGETYPE_WEBP => 'image/webp',
    ];
    private const PATH_PATTERN = '#^uploads/(?:gallery|programs|news|impact|team|site|seo)/\d{4}/\d{2}/[a-f0-9]{32}(?:-thumb|-md)?\.(?:webp|jpg)$#';

    /** Longest edge of the medium variant used by srcset (phones, tablets, cards on small screens). */
    public const MEDIUM_EDGE = 1280;

    private readonly string $publicRoot;

    public function __construct(?string $publicRoot = null, private readonly bool $requireUploadedFile = true)
    {
        $this->publicRoot = rtrim($publicRoot ?? PUBLIC_ROOT, '/\\');
    }

    /**
     * @param array{name?: mixed, type?: mixed, tmp_name?: mixed, error?: mixed, size?: mixed} $file
     * @throws UploadException
     */
    public function store(array $file, string $folder): StoredImage
    {
        if (!in_array($folder, self::FOLDERS, true)) {
            throw new UploadException('Invalid upload folder.');
        }
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

        $bytes = filesize($tmp);
        $maxBytes = max(1, (int) config('app.uploads.image_max_mb', 8)) * 1024 * 1024;
        if ($bytes === false || $bytes === 0) {
            throw new UploadException('The uploaded file is empty.');
        }
        if ($bytes > $maxBytes) {
            throw new UploadException('The image is too large (maximum ' . (int) config('app.uploads.image_max_mb', 8) . ' MB).');
        }

        $mime = $this->detectMime($tmp);
        if (!in_array($mime, self::MIME_BY_TYPE, true)) {
            throw new UploadException('Only JPEG, PNG or WebP images are allowed.');
        }
        $info = @getimagesize($tmp);
        if ($info === false || (self::MIME_BY_TYPE[$info[2]] ?? null) !== $mime) {
            throw new UploadException('The file is not a valid image.');
        }
        [$width, $height] = [(int) $info[0], (int) $info[1]];
        if ($width < 1 || $height < 1) {
            throw new UploadException('The file is not a valid image.');
        }
        self::assertDimensions($width, $height);

        $webp = (imagetypes() & IMG_WEBP) === IMG_WEBP;
        $ext = $webp ? 'webp' : 'jpg';
        $sub = gmdate('Y/m');
        $dir = $this->publicRoot . '/uploads/' . $folder . '/' . $sub;
        $this->ensureDirectory($dir);
        $name = bin2hex(random_bytes(16));
        $mainFile = "{$dir}/{$name}.{$ext}";
        $thumbFile = "{$dir}/{$name}-thumb.{$ext}";
        $mediumFile = "{$dir}/{$name}-md.{$ext}";
        $written = [];

        $source = null;
        try {
            $source = self::decodeFile($tmp, $mime);
            if ($mime === 'image/jpeg') {
                $source = self::orient($source, $tmp);
            }
            $maxEdge = max(16, (int) config('app.uploads.image_max_edge', 1920));
            $thumbEdge = max(16, (int) config('app.uploads.thumb_max_edge', 720));

            $main = $this->resize($source, $maxEdge, !$webp);
            $this->writeAtomic($main, $mainFile, $webp);
            $written[] = $mainFile;
            // The medium copy only exists when it is meaningfully smaller than the main image.
            if (max(imagesx($main), imagesy($main)) > self::MEDIUM_EDGE * 1.15) {
                $medium = $this->resize($main, self::MEDIUM_EDGE, !$webp);
                $this->writeAtomic($medium, $mediumFile, $webp);
                $written[] = $mediumFile;
                $medium = null;
            }
            $thumb = $this->resize($main, $thumbEdge, !$webp);
            $this->writeAtomic($thumb, $thumbFile, $webp);
            $written[] = $thumbFile;
            $outW = imagesx($main);
            $outH = imagesy($main);
        } catch (\Throwable $e) {
            foreach ($written as $path) {
                @unlink($path);
            }
            if ($e instanceof UploadException) {
                throw $e;
            }
            throw new UploadException('The image could not be processed.', 0, $e);
        } finally {
            $source = $main = $thumb = null;
        }

        $relative = 'uploads/' . $folder . '/' . $sub . '/' . $name;
        return new StoredImage("{$relative}.{$ext}", "{$relative}-thumb.{$ext}", $outW, $outH);
    }

    public function delete(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '' || preg_match(self::PATH_PATTERN, $relativePath) !== 1) {
            return;
        }
        $uploads = realpath($this->publicRoot . '/uploads');
        $real = realpath($this->publicRoot . '/' . $relativePath);
        if ($uploads === false || $real === false || !str_starts_with($real, $uploads . DIRECTORY_SEPARATOR)) {
            return;
        }
        if (is_file($real)) {
            @unlink($real);
        }
    }

    public function deleteImage(?string $path, ?string $thumb): void
    {
        $this->delete($path);
        $this->delete($thumb);
        if ($path !== null && $path !== '') {
            $this->delete(self::variant($path, 'md'));
        }
    }

    /**
     * Gives a stored image (all its sizes) a new random file name and returns the new paths.
     * Used when a photo is unpublished or consent is withdrawn: the old public URL stops
     * working at once instead of staying reachable for anyone who saved it.
     *
     * @return array{path: string, thumb: string}|null null when the files cannot be renamed
     */
    public function rename(?string $path, ?string $thumb): ?array
    {
        if ($path === null || preg_match(self::PATH_PATTERN, $path) !== 1 || ($thumb !== null && $thumb !== '' && preg_match(self::PATH_PATTERN, $thumb) !== 1)) {
            return null;
        }
        $uploads = realpath($this->publicRoot . '/uploads');
        $mainFile = realpath($this->publicRoot . '/' . $path);
        if ($uploads === false || $mainFile === false || !str_starts_with($mainFile, $uploads . DIRECTORY_SEPARATOR)) {
            return null;
        }
        $newName = bin2hex(random_bytes(16));
        $newPath = (string) preg_replace('/[a-f0-9]{32}\.(webp|jpg)$/', $newName . '.$1', $path);
        $moves = [$path => $newPath, self::variant($path, 'md') => self::variant($newPath, 'md')];
        if ($thumb !== null && $thumb !== '') {
            $moves[$thumb] = self::variant($newPath, 'thumb');
        }
        foreach ($moves as $from => $to) {
            $source = $this->publicRoot . '/' . $from;
            if (is_file($source) && !@rename($source, $this->publicRoot . '/' . $to)) {
                return null;
            }
        }
        return ['path' => $newPath, 'thumb' => ($thumb !== null && $thumb !== '') ? self::variant($newPath, 'thumb') : ''];
    }

    /** Path of a derived size of a stored main image: variant('…/abc.webp', 'md') → '…/abc-md.webp'. */
    public static function variant(string $path, string $suffix): string
    {
        return (string) preg_replace('/(?:-thumb|-md)?\.(webp|jpg)$/', '-' . $suffix . '.$1', $path);
    }

    private function detectMime(string $file): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file);
        return is_string($mime) ? $mime : '';
    }

    /** @throws UploadException */
    public static function assertDimensions(int $width, int $height): void
    {
        if ($width > self::MAX_EDGE_ABSOLUTE || $height > self::MAX_EDGE_ABSOLUTE
            || $width * $height > (int) config('app.uploads.image_max_pixels', 40_000_000)) {
            throw new UploadException('The image dimensions are too large.');
        }
        self::assertMemory($width, $height);
    }

    private static function assertMemory(int $width, int $height): void
    {
        $limit = ini_get('memory_limit');
        if ($limit === false || $limit === '' || $limit === '-1') {
            return;
        }
        $bytes = (int) $limit;
        $unit = strtolower(substr(trim($limit), -1));
        $bytes *= match ($unit) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        };
        // GD holds the decoded image (4 bytes/pixel) plus a resized copy and headroom.
        $needed = (int) ($width * $height * 4.5) + memory_get_usage();
        if ($bytes > 0 && $needed > $bytes) {
            throw new UploadException('The image is too large to process. Please upload a smaller one.');
        }
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new UploadException('The server could not create the upload folder. Please contact the site administrator.');
        }
    }

    /** @throws UploadException */
    public static function decodeFile(string $file, string $mime): GdImage
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file),
            'image/png' => @imagecreatefrompng($file),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false,
            default => false,
        };
        if (!$image instanceof GdImage) {
            throw new UploadException('The image is damaged or unsupported.');
        }
        return $image;
    }

    /** Applies the EXIF orientation of a JPEG so the pixels are upright once metadata is stripped. */
    public static function orient(GdImage $image, string $file): GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data($file, 'IFD0');
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        if ($orientation < 2 || $orientation > 8) {
            return $image;
        }
        // imagerotate() turns counter-clockwise; 270 therefore means 90 degrees clockwise.
        switch ($orientation) {
            case 2:
                imageflip($image, IMG_FLIP_HORIZONTAL);
                return $image;
            case 3:
                return self::rotate($image, 180);
            case 4:
                imageflip($image, IMG_FLIP_VERTICAL);
                return $image;
            case 5:
                imageflip($image, IMG_FLIP_VERTICAL);
                return self::rotate($image, 270);
            case 6:
                return self::rotate($image, 270);
            case 7:
                imageflip($image, IMG_FLIP_HORIZONTAL);
                return self::rotate($image, 270);
            default:
                return self::rotate($image, 90);
        }
    }

    private static function rotate(GdImage $image, int $angle): GdImage
    {
        $rotated = imagerotate($image, $angle, 0);
        if (!$rotated instanceof GdImage) {
            throw new UploadException('The image could not be processed.');
        }
        return $rotated;
    }

    /** Copies into a fresh true-colour canvas no larger than $maxEdge, never upscaling. */
    private function resize(GdImage $source, int $maxEdge, bool $flattenOnWhite): GdImage
    {
        $w = imagesx($source);
        $h = imagesy($source);
        $scale = min(1.0, $maxEdge / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $canvas = imagecreatetruecolor($nw, $nh);
        if (!$canvas instanceof GdImage) {
            throw new UploadException('The image could not be processed.');
        }
        if ($flattenOnWhite) {
            imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
            imagealphablending($canvas, true);
        } else {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefill($canvas, 0, 0, (int) imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        }
        if (!imagecopyresampled($canvas, $source, 0, 0, 0, 0, $nw, $nh, $w, $h)) {
            throw new UploadException('The image could not be processed.');
        }
        return $canvas;
    }

    private function writeAtomic(GdImage $image, string $target, bool $webp): void
    {
        $tmp = dirname($target) . '/.tmp-' . bin2hex(random_bytes(8));
        try {
            if (!$webp) {
                imageinterlace($image, true); // progressive JPEG: renders early on slow connections
            }
            $ok = $webp ? imagewebp($image, $tmp, 82) : imagejpeg($image, $tmp, 85);
            if (!$ok || !is_file($tmp) || filesize($tmp) === 0) {
                throw new UploadException('The image could not be saved.');
            }
            @chmod($tmp, 0644);
            if (!@rename($tmp, $target)) {
                throw new UploadException('The image could not be saved.');
            }
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }
}
