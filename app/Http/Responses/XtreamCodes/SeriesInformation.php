<?php

namespace App\Http\Responses\XtreamCodes;

final readonly class SeriesInformation
{
    /**
     * @param  array<string, Episode[]>  $seasonsWithEpisodes
     */
    public function __construct(
        public int $seriesId,
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
        public int $rating_5based,
        public array $backdropPath,
        public string $youtubeTrailer,
        public string $episodeRunTime,
        public string $categoryId,
        public array $seasonsWithEpisodes
    ) {}

    /**
     * Create a new instance from JSON data
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
            $info['backdrop_path'],
            $info['youtube_trailer'],
            $info['episode_run_time'],
            $info['category_id'],
            collect($data['episodes'])->map(
                fn (array $episodes) => collect($episodes)
                    ->map(fn (array $episode) => Episode::fromJson($episode))
            )->toArray()
        );
    }
}
