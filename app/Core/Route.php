<?php

declare(strict_types=1);

namespace App\Core;

final class Route
{
    public bool $csrf = true;

    /** @var list<string> */
    public array $middleware;

    private string $regex;

    /** @var list<string> */
    private array $paramNames = [];

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param list<string> $middleware
     */
    public function __construct(
        public readonly string $method,
        public readonly string $pattern,
        public readonly array $handler,
        array $middleware = [],
    ) {
        $this->middleware = $middleware;
        $this->regex = $this->compile($pattern);
    }

    /** Only for endpoints authenticated by other means (e.g. a signed webhook). */
    public function withoutCsrf(): self
    {
        $this->csrf = false;
        return $this;
    }

    /** @param string|list<string> $middleware */
    public function middleware(string|array $middleware): self
    {
        foreach ((array) $middleware as $m) {
            $this->middleware[] = $m;
        }
        return $this;
    }

    /** @return array<string, string>|null Matched parameters, or null. */
    public function match(string $path): ?array
    {
        if (!preg_match($this->regex, $path, $m)) {
            return null;
        }
        $params = [];
        foreach ($this->paramNames as $name) {
            $params[$name] = $m[$name];
        }
        return $params;
    }

    private function compile(string $pattern): string
    {
        $parts = preg_split('/(\{[a-z_]+(?::[^}]+)?\})/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $regex = '';
        foreach ($parts as $part) {
            if (!preg_match('/^\{([a-z_]+)(?::([^}]+))?\}$/', $part, $m)) {
                $regex .= preg_quote($part, '#');
                continue;
            }
            $this->paramNames[] = $m[1];
            $constraint = ($m[2] ?? '') !== '' ? $m[2] : match (true) {
                $m[1] === 'id' || str_ends_with($m[1], '_id') => '[1-9][0-9]{0,9}',
                $m[1] === 'token' => '[a-f0-9]{64}',
                default => '[a-z0-9]+(?:-[a-z0-9]+)*',
            };
            $regex .= '(?P<' . $m[1] . '>' . $constraint . ')';
        }
        return '#^' . $regex . '$#D';
    }
}
