<?php

namespace App\Http\Responses\XtreamCodes;

final readonly class Episode
{
    public function __construct(
        public string $id,
        public int $episodeNum,
        public string $title,
        public string $containerExtension,
        public int $durationSecs,
        public string $duration,
        public VideoMetadata $video,
        public AudioMetadata $audio,
        public int $bitrate,
        public string $customSid,
        public string $added,
        public int $season,
        public string $directSource
    ) {}

    public static function fromJson(array $data): self
    {
        $info = $data['info'];

        return new self(
            $data['id'],
            $data['episode_num'],
            $data['title'],
            $data['container_extension'],
            $info['duration_secs'],
            $info['duration'],
            VideoMetadata::fromJson($info['video']),
            AudioMetadata::fromJson($info['audio']),
            $info['bitrate'],
            $data['custom_sid'],
            $data['added'],
            $data['season'],
            $data['direct_source']
        );
    }
}
