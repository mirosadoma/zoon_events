<?php

namespace App\Modules\AdminConsole\Application\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class InertiaListPaginator
{
    public const PER_PAGE = 15;

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return array{items: Collection<int, TModel>, pagination: array{page: int, per_page: int, total: int, last_page: int}}
     */
    public static function paginate(Builder $query, Request $request, int $perPage = self::PER_PAGE): array
    {
        $perPage = max(1, min(100, $perPage));
        $page = max(1, (int) $request->integer('page', 1));
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => $paginator->getCollection(),
            'pagination' => self::meta($paginator),
        ];
    }

    /** @return array{page: int, per_page: int, total: int, last_page: int} */
    public static function meta(LengthAwarePaginator $paginator): array
    {
        return [
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => max(1, $paginator->lastPage()),
        ];
    }

    /** @return array{page: int, per_page: int, total: int, last_page: int} */
    public static function empty(): array
    {
        return [
            'page' => 1,
            'per_page' => self::PER_PAGE,
            'total' => 0,
            'last_page' => 1,
        ];
    }
}
