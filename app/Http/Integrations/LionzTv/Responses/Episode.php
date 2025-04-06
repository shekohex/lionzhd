<?php

declare(strict_types=1);

namespace App\Http\Integrations\LionzTv\Responses;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class Episode
{
    public function __construct(
        public string $id,
        public int $episodeNum,
        public string $title,
        public string $containerExtension,
        public int $durationSecs,
        public string $duration,
        public ?VideoMetadata $video,
        public ?AudioMetadata $audio,
        public int $bitrate,
        public string $customSid,
        public string $added,
        public int $season,
        public string $directSource
    ) {}

    /**
     * @param  array<string,mixed>  $data
     */
    public static function fromJson(array $data): self
    {
        $info = $data['info'];

        return new self(
            $data['id'],
            $data['episode_num'],
            $data['title'],
            $data['container_extension'],
            $info['duration_secs'] ?? 0,
            $info['duration'] ?? '0:00:00',
            array_key_exists('video', $info ?? []) && is_array($info['video']) ? VideoMetadata::fromJson($info['video'] ?? []) : null,
            array_key_exists('audio', $info ?? []) && is_array($info['audio']) ? AudioMetadata::fromJson($info['audio'] ?? []) : null,
            $info['bitrate'] ?? 0,
            $data['custom_sid'] ?? '',
            $data['added'] ?? '',
            $data['season'] ?? 0,
            $data['direct_source'] ?? ''
        );
    }
}
