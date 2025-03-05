<?php

namespace App\Http\Responses\XtreamCodes;

final readonly class VodInformation
{
    public function __construct(
        public int $vodId,
        public string $movieImage,
        public string $tmdbId,
        public string $backdrop,
        public string $youtubeTrailer,
        public string $genre,
        public string $plot,
        public string $cast,
        public string $rating,
        public string $director,
        public string $releaseDate,
        public array $backdropPath,
        public int $durationSecs,
        public string $duration,
        public VideoMetadata $video,
        public AudioMetadata $audio,
        public int $bitrate,
        public Movie $movie
    ) {}

    public static function fromJson(int $vodId, array $data): self
    {
        $info = $data['info'];

        return new self(
            $vodId,
            $info['movie_image'],
            $info['tmdb_id'],
            $info['backdrop'],
            $info['youtube_trailer'],
            $info['genre'],
            $info['plot'],
            $info['cast'],
            $info['rating'],
            $info['director'],
            $info['releasedate'],
            $info['backdrop_path'],
            $info['duration_secs'],
            $info['duration'],
            VideoMetadata::fromJson($info['video']),
            AudioMetadata::fromJson($info['audio']),
            $info['bitrate'],
            Movie::fromJson($data['movie_data'])
        );
    }
}
