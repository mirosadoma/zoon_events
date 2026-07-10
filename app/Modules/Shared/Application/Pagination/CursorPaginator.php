<?php

namespace App\Modules\Shared\Application\Pagination;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class CursorPaginator
{
    /** @param array<string, mixed> $filters */
    public function paginate(
        Builder $query,
        string $scope,
        array $filters,
        ?string $cursor,
        int $pageSize,
        string $orderColumn = 'created_at',
    ): CursorPage {
        $pageSize = min(max($pageSize, 1), 100);

        if (is_string($cursor) && $cursor !== '') {
            $position = SignedCursor::decode($cursor, $scope, $filters);
            $query->where(function (Builder $builder) use ($position, $orderColumn): void {
                $builder->where($orderColumn, '<', $position->createdAt)
                    ->orWhere(function (Builder $sameTime) use ($position, $orderColumn): void {
                        $sameTime->where($orderColumn, '=', $position->createdAt)
                            ->where('id', '<', $position->id);
                    });
            });
        }

        $records = $query
            ->reorder($orderColumn, 'desc')
            ->orderByDesc('id')
            ->limit($pageSize + 1)
            ->get();
        $hasMore = $records->count() > $pageSize;
        $items = $records->take($pageSize)->values();
        /** @var Model|null $last */
        $last = $items->last();
        $nextCursor = $hasMore && $last instanceof Model
            ? SignedCursor::issue(
                $last->getAttribute($orderColumn)->toISOString(),
                (string) $last->getKey(),
                $scope,
                $filters,
            )
            : null;

        return new CursorPage($items->all(), $nextCursor, $hasMore, $items->count());
    }
}
