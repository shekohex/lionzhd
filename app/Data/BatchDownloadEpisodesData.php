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
    /**
     * @param  DataCollection<SelectedEpisodeData>  $selectedEpisodes
     */
    public function __construct(
        #[DataCollectionOf(SelectedEpisodeData::class)]
        public DataCollection $selectedEpisodes,
    ) {}
}
