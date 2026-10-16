<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    /** @var array<string, string> */
    private array $routeParams = [];

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @param array<string, mixed> $server
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $post,
        private readonly array $files,
        private readonly array $server,
        private readonly ?string $rawBody = null,
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string) (parse_url($uri, PHP_URL_PATH) ?? '/');
        $path = '/' . trim(rawurldecode($path), '/');

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            $path,
            $_GET,
            $_POST,
            $_FILES,
            $_SERVER,
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /** Normalised path without trailing slash, e.g. "/programs/web-design". */
    public function path(): string
    {
        return $this->path;
    }

    /** A trimmed string from POST (or GET when not present); arrays yield ''. */
    public function input(string $key, string $default = ''): string
    {
        $value = $this->post[$key] ?? $this->query[$key] ?? $default;
        return is_string($value) ? trim($value) : $default;
    }

    /** A trimmed string from the query string only. */
    public function query(string $key, string $default = ''): string
    {
        $value = $this->query[$key] ?? $default;
        return is_string($value) ? trim($value) : $default;
    }

    public function queryInt(string $key, int $default = 0): int
    {
        $value = $this->query($key);
        return ctype_digit($value) && strlen($value) < 10 ? (int) $value : $default;
    }

    /** @return array<string, mixed> */
    public function post(): array
    {
        return $this->post;
    }

    /** @return array<string, mixed> */
    public function queryAll(): array
    {
        return $this->query;
    }

    /** @return list<string> Values of a multi-value field like interests[] (strings only). */
    public function inputList(string $key): array
    {
        $value = $this->post[$key] ?? [];
        if (!is_array($value)) {
            return [];
        }
        return array_values(array_map('trim', array_filter($value, 'is_string')));
    }

    /**
     * A single uploaded file entry, or null when nothing was chosen.
     *
     * @return array{name: string, type: string, tmp_name: string, error: int, size: int}|null
     */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
            return null;
        }
        if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return [
            'name' => (string) ($file['name'] ?? ''),
            'type' => (string) ($file['type'] ?? ''),
            'tmp_name' => (string) ($file['tmp_name'] ?? ''),
            'error' => (int) $file['error'],
            'size' => (int) ($file['size'] ?? 0),
        ];
    }

    /**
     * Normalises a multiple file input (name="images[]") into a list of entries.
     *
     * @return list<array{name: string, type: string, tmp_name: string, error: int, size: int}>
     */
    public function files(string $key): array
    {
        $raw = $this->files[$key] ?? null;
        if (!is_array($raw) || !isset($raw['error'])) {
            return [];
        }
        if (!is_array($raw['error'])) {
            $single = $this->file($key);
            return $single === null ? [] : [$single];
        }
        $list = [];
        foreach ($raw['error'] as $i => $error) {
            if ((int) $error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $list[] = [
                'name' => (string) ($raw['name'][$i] ?? ''),
                'type' => (string) ($raw['type'][$i] ?? ''),
                'tmp_name' => (string) ($raw['tmp_name'][$i] ?? ''),
                'error' => (int) $error,
                'size' => (int) ($raw['size'][$i] ?? 0),
            ];
        }
        return $list;
    }

    public function header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if ($name === 'Content-Type') {
            $key = 'CONTENT_TYPE';
        }
        $value = $this->server[$key] ?? '';
        return is_string($value) ? $value : '';
    }

    public function rawBody(): string
    {
        return $this->rawBody ?? (string) file_get_contents('php://input');
    }

    public function ip(): string
    {
        $remote = (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
        $trusted = (array) config('app.trusted_proxies', []);
        if ($trusted !== [] && in_array($remote, $trusted, true)) {
            $forwarded = explode(',', (string) ($this->server['HTTP_X_FORWARDED_FOR'] ?? ''));
            $candidate = trim((string) end($forwarded));
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }
        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
    }

    public function userAgent(): string
    {
        return mb_substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function isSecure(): bool
    {
        $https = strtolower((string) ($this->server['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off') {
            return true;
        }
        $remote = (string) ($this->server['REMOTE_ADDR'] ?? '');
        $trusted = (array) config('app.trusted_proxies', []);
        return in_array($remote, $trusted, true)
            && strtolower((string) ($this->server['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    /** True for the progressive-enhancement fetch() calls made by main.js. */
    public function wantsFragment(): bool
    {
        return ($this->server['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
    }

    /** @param array<string, string> $params */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function route(string $key): string
    {
        return $this->routeParams[$key] ?? '';
    }

    /** Path plus the current query string, for "return to" links. */
    public function fullPath(): string
    {
        $qs = http_build_query($this->query);
        return $this->path . ($qs !== '' ? '?' . $qs : '');
    }
}
