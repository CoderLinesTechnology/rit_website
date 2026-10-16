<?php

declare(strict_types=1);

namespace App\Services;

/** Result of DocumentStore::store(). The path is relative to the private storage root. */
final readonly class StoredDocument
{
    public function __construct(
        public string $path,
        public string $originalName,
        public int $size,
        public string $mime = 'application/pdf',
    ) {
    }
}
