<?php

declare(strict_types=1);

namespace App\Services;

/** Result of ImageUploader::store(). Paths are relative to public/. */
final readonly class StoredImage
{
    public function __construct(
        public string $path,
        public string $thumbPath,
        public int $width,
        public int $height,
    ) {
    }
}
