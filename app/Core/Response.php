<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    /**
     * The only inline script on the site: flags that JavaScript is available
     * before first paint (so menus can collapse without a flash). It is allowed
     * by its exact hash in the CSP; everything else is loaded from /assets.
     */
    public const JS_FLAG_SCRIPT = "document.documentElement.classList.add('js')";

    /** Extra origins a single page may frame (e.g. a verified map embed). */
    private array $frameSources = [];

    /** Extra origins a form submission may end up at (e.g. a payment provider's hosted checkout). */
    private array $formActions = [];

    /** @param array<string, string> $headers */
    public function __construct(
        private string $body = '',
        private int $status = 200,
        private array $headers = [],
    ) {
        // Header values can never carry line breaks or other control characters.
        foreach ($this->headers as $name => $value) {
            $this->headers[$name] = (string) preg_replace('/[\x00-\x1F\x7F]/', '', (string) $value);
        }
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /** Always 303 after a POST so the browser follows with GET (PRG). */
    public static function redirect(string $to, int $status = 303): self
    {
        // Only same-site relative paths or our own absolute URL are allowed.
        if (preg_match('/[\x00-\x20\x7F]/', $to) === 1) {
            $to = '/';
        }
        if (!str_starts_with($to, '/') || str_starts_with($to, '//') || str_contains($to, '\\')) {
            $base = (string) config('app.url');
            if (!str_starts_with($to, $base . '/') && $to !== $base) {
                $to = '/';
            }
        }
        return new self('', $status, ['Location' => $to]);
    }

    /** @param array<mixed> $data */
    public static function json(array $data, int $status = 200): self
    {
        return new self(
            (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_THROW_ON_ERROR),
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }

    public static function text(string $body, string $contentType = 'text/plain; charset=UTF-8', int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => $contentType]);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = (string) preg_replace('/[\x00-\x1F\x7F]/', '', $value);
        return $this;
    }

    public function allowFrameSource(string $origin): self
    {
        if (preg_match('#^https://[a-z0-9.-]+$#i', $origin)) {
            $this->frameSources[] = $origin;
        }
        return $this;
    }

    /** Browsers apply form-action to redirects after a POST, so the checkout origin must be listed. */
    public function allowFormAction(string $origin): self
    {
        if (preg_match('#^https://[a-z0-9.-]+(:\d+)?$#i', $origin)) {
            $this->formActions[] = $origin;
        }
        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function header(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers + $this->securityHeaders();
    }

    /** @return array<string, string> */
    private function securityHeaders(): array
    {
        $frame = $this->frameSources === [] ? "'none'" : implode(' ', array_unique($this->frameSources));
        $headers = [
            'Content-Security-Policy' => implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'sha256-" . base64_encode(hash('sha256', self::JS_FLAG_SCRIPT, true)) . "'",
                "style-src 'self'",
                "img-src 'self' data:",
                "font-src 'self'",
                "connect-src 'self'",
                "frame-src {$frame}",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'" . ($this->formActions === [] ? '' : ' ' . implode(' ', array_unique($this->formActions))),
                "frame-ancestors 'none'",
            ]),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];
        if (config('app.force_https') && config('app.env') === 'production') {
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }
        return $headers;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            header_remove('X-Powered-By');
            foreach ($this->headers() as $name => $value) {
                header($name . ': ' . $value);
            }
        }
        echo $this->body;
    }
}
