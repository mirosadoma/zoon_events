<?php

namespace App\Modules\Shared\Application\Pagination;

final readonly class CursorPage
{
    public function __construct(
        public array $items,
        public ?string $nextCursor,
        public bool $hasMore,
        public int $pageSize,
    ) {}
}
