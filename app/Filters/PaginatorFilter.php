<?php

declare(strict_types=1);

namespace App\Filters;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Laravel\Scout\Builder;

/**
 * @template TModel of Model
 */
final readonly class PaginatorFilter
{
    public function __construct(private int $page = 1, private int $perPage = 10) {}

    /**
     * @param  Builder<TModel>  $query
     * @param  Closure(LengthAwarePaginator<TModel>): mixed  $next
     */
    public function __invoke(Builder $query, Closure $next): mixed
    {
        $paginator = $query->paginate(
            page: $this->page,
            perPage: $this->perPage,
        );

        return $next($paginator);
    }
}
