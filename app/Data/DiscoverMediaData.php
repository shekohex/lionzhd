<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class DiscoverMediaData extends Data
{
    public function __construct(
        /**
         * @var DataCollection<VodStreamData|InWatchlistData>
         */
        public DataCollection $movies,
        /**
         * @var DataCollection<SeriesData|InWatchlistData>
         */
        public DataCollection $series,
    ) {}
}
