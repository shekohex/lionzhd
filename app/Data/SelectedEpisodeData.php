<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class SelectedEpisodeData extends Data
{
    public function __construct(
        public int $season,
        public int $episodeNum,
    ) {}
}
