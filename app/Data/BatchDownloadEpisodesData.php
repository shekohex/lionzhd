<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class BatchDownloadEpisodesData extends Data
{
    public function __construct(
        /** @var DataCollection<SelectedEpisodeData> */
        #[DataCollectionOf(SelectedEpisodeData::class)]
        public DataCollection $selectedEpisodes,
    ) {}
}
