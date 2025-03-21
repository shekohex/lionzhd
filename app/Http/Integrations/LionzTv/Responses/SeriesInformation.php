<?php

declare(strict_types=1);

namespace App\Http\Integrations\LionzTv\Responses;

use Illuminate\Support\Arr;
use Spatie\TypeScriptTransformer\Attributes\RecordTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class SeriesInformation
{
    public function __construct(
        public int $seriesId,
        /** @var array<string> */
        public array $seasons,
        public string $name,
        public string $cover,
        public string $plot,
        public string $cast,
        public string $director,
        public string $genre,
        public string $releaseDate,
        public string $lastModified,
        public string $rating,
        public float $rating_5based,
        /** @var array<string> */
        public array $backdropPath,
        public string $youtubeTrailer,
        public string $episodeRunTime,
        public string $categoryId,
        /** @var array<string, Episode[]> */
        #[RecordTypeScriptType('string', Episode::class, array: true)]
        public array $seasonsWithEpisodes
    ) {}

    /**
     * Create a new instance from JSON data
     *
     * @param  array<string,mixed>  $data
     */
    public static function fromJson(int $seriesId, array $data): self
    {
        $info = $data['info'];

        return new self(
            $seriesId,
            $data['seasons'],
            $info['name'],
            $info['cover'],
            $info['plot'],
            $info['cast'],
            $info['director'],
            $info['genre'],
            $info['releaseDate'],
            $info['last_modified'],
            $info['rating'],
            $info['rating_5based'],
            Arr::wrap($info['backdrop_path']),
            $info['youtube_trailer'],
            $info['episode_run_time'],
            $info['category_id'] ?? '',
            collect($data['episodes'])->map(
                static fn (array $episodes) => collect($episodes)
                    ->map(static fn (array $episode) => Episode::fromJson($episode))
            )->toArray()
        );
    }
}
