<?php
declare(strict_types=1);

namespace App\Core;

/** Paginación simple: calcula desplazamiento y conserva los filtros de la consulta en los enlaces. */
final class Pager
{
    public function __construct(
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
    ) {
    }

    public static function make(int $total, mixed $page, int $perPage = 15): self
    {
        $pages = max(1, (int) ceil($total / $perPage));
        return new self($total, min(max(1, (int) $page), $pages), $perPage);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function pages(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function link(int $page, array $query, string $path): string
    {
        $query['pagina'] = $page;
        return url($path) . '?' . http_build_query(array_filter($query, fn($v) => $v !== '' && $v !== null));
    }
}
