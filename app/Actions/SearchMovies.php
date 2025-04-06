<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\AsAction;
use App\Enums\MediaType;
use App\Enums\SearchSortby;
use App\Filters\LightweightSearchFilter;
use App\Filters\MoviesSortByFilter;
use App\Filters\PaginatorFilter;
use App\Models\VodStream;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Pipeline;

final class SearchMovies
{
    use AsAction;

    public function __invoke(
        ?string $query,
        ?SearchSortby $sortBy = null,
        int $page = 1,
        int $perPage = 10,
        bool $lightweight = false
    ): LengthAwarePaginator {

        $pipes = [
            new MoviesSortByFilter($sortBy),
            new PaginatorFilter($page, $perPage),
        ];

        when($lightweight, fn () => $pipes[] = new LightweightSearchFilter(MediaType::Movie));

        return Pipeline::send(VodStream::search($query))
            ->through($pipes)
            ->thenReturn()
            ->withQueryString();
    }
}
