<?php

declare(strict_types=1);

namespace App\Filters;

use App\Enums\SearchSortby;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;

/**
 * @template TModel of Model
 */
final readonly class MoviesSortByFilter
{
    public function __construct(private ?SearchSortby $filter) {}

    /**
     * @param  Builder<TModel>  $query
     * @param  Closure(Builder<TModel>): mixed  $next
     */
    public function __invoke(Builder $query, Closure $next): mixed
    {
        if (! $this->filter instanceof SearchSortby) {
            return $next($query);
        }

        $query = match ($this->filter) {
            SearchSortby::Popular => $query->orderBy('rating_5based', 'desc'),
            SearchSortby::Latest => $query->orderBy('added', 'desc'),
            SearchSortby::Rating => $query->orderBy('rating_5based', 'desc'),
        };

        return $next($query);
    }
}
