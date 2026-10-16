<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Thrown anywhere to stop the request with an HTTP error page.
 * The message is shown to the visitor, so keep it free of internals.
 */
final class HttpException extends \RuntimeException
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly int $status,
        string $message = '',
        public readonly array $headers = [],
    ) {
        parent::__construct($message, $status);
    }

    public static function notFound(string $message = ''): self
    {
        return new self(404, $message);
    }

    public static function forbidden(string $message = ''): self
    {
        return new self(403, $message);
    }
}
