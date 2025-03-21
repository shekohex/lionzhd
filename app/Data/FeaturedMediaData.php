<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class FeaturedMediaData extends Data
{
    public function __construct(
        /**
         * @var DataCollection<VodStreamData>
         */
        #[DataCollectionOf(VodStreamData::class)]
        public DataCollection $movies,
        /**
         * @var DataCollection<SeriesData>
         */
        #[DataCollectionOf(SeriesData::class)]
        public DataCollection $series,
    ) {}
}
