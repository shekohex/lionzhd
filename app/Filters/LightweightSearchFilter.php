<?php

declare(strict_types=1);

namespace App\Filters;

use App\Enums\MediaType;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @template TModel of Model
 */
final readonly class LightweightSearchFilter
{
    public function __construct(private MediaType $kind) {}

    /**
     * @param  LengthAwarePaginator<TModel>  $paginator
     * @param  Closure(LengthAwarePaginator<TModel>): mixed  $next
     */
    public function __invoke(LengthAwarePaginator $paginator, Closure $next): mixed
    {
        $results = $paginator->through(fn ($model) => [
            'num' => $model->num,
            'name' => $model->name,
            'type' => $this->kind,
            'poster' => $model->cover,
        ]);

        return $next($results);
    }
}
