<?php

namespace App\Modules\Shared\Application\Pagination;

final readonly class CursorPageCursor
{
    public function __construct(
        public string $createdAt,
        public string $id,
    ) {}
}
