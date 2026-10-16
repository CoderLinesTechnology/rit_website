<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Page-number pagination for listings (the template partial
 * partials/pagination renders the links). Out-of-range pages are clamped.
 */
final class Paginator
{
    public readonly int $page;
    public readonly int $lastPage;
    public readonly int $offset;

    /** @param array<string, mixed> $query Current query string (filters are kept in links). */
    public function __construct(
        public readonly int $total,
        int $page,
        public readonly int $perPage,
        private readonly string $path,
        private readonly array $query = [],
    ) {
        $this->lastPage = max(1, (int) ceil($total / max(1, $perPage)));
        $this->page = min(max(1, $page), $this->lastPage);
        $this->offset = ($this->page - 1) * $perPage;
    }

    public static function fromRequest(Request $request, int $total, int $perPage): self
    {
        $query = array_filter($request->queryAll(), static fn ($v, $k): bool => is_string($v) && $v !== '' && $k !== 'page' && $k !== 'partial', ARRAY_FILTER_USE_BOTH);
        return new self($total, $request->queryInt('page', 1), $perPage, $request->path(), $query);
    }

    public function url(int $page): string
    {
        $query = $this->query;
        if ($page > 1) {
            $query['page'] = $page;
        }
        return url($this->path, $query);
    }

    public function hasMore(): bool
    {
        return $this->page < $this->lastPage;
    }

    public function nextUrl(): ?string
    {
        return $this->hasMore() ? $this->url($this->page + 1) : null;
    }

    public function previousUrl(): ?string
    {
        return $this->page > 1 ? $this->url($this->page - 1) : null;
    }
}
