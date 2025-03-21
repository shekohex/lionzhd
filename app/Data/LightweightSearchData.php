<?php

declare(strict_types=1);

namespace App\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class LightweightSearchData extends Data
{
    public function __construct(
        /**
         * @var LengthAwarePaginator<VodStreamData>
         */
        public LengthAwarePaginator $movies,
        /**
         * @var LengthAwarePaginator<SeriesData>
         */
        public LengthAwarePaginator $series,

        public LightweightSearchFiltersData $filters,
    ) {}
}
