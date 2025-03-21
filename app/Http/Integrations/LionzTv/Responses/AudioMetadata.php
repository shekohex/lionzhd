<?php

declare(strict_types=1);

namespace App\Http\Integrations\LionzTv\Responses;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Spatie\TypeScriptTransformer\Attributes\TypeScriptType;

#[TypeScript]
final readonly class AudioMetadata
{
    public function __construct(
        public int $index,
        public string $codecName,
        public string $codecLongName,
        public string $profile,
        public string $codecType,
        public string $codecTimeBase,
        public string $codecTagString,
        public string $codecTag,
        public string $sampleFmt,
        public string $sampleRate,
        public int $channels,
        public string $channelLayout,
        public int $bitsPerSample,
        public string $rFrameRate,
        public string $avgFrameRate,
        public string $timeBase,
        public int $startPts,
        public string $startTime,
        public int $durationTs,
        public string $duration,
        public string $bitRate,
        public string $maxBitRate,
        public string $nbFrames,
        public Disposition $disposition,
        /** @var array{language:string,DURATION:string} */
        #[TypeScriptType(['language' => 'string', 'DURATION' => 'string'])]
        public array $tags
    ) {}

    /**
     * @param  array<string,mixed>  $data
     */
    public static function fromJson(array $data): self
    {
        return new self(
            $data['index'],
            $data['codec_name'],
            $data['codec_long_name'],
            $data['profile'] ?? '',
            $data['codec_type'],
            $data['codec_time_base'],
            $data['codec_tag_string'],
            $data['codec_tag'],
            $data['sample_fmt'],
            $data['sample_rate'],
            $data['channels'],
            $data['channel_layout'],
            $data['bits_per_sample'],
            $data['r_frame_rate'],
            $data['avg_frame_rate'],
            $data['time_base'],
            $data['start_pts'],
            $data['start_time'],
            $data['duration_ts'] ?? 0, // 0 seconds
            $data['duration'] ?? '00:00:00.000',
            $data['bit_rate'] ?? '5000000', // 5 Mbps is a standard bitrate for audio
            $data['max_bit_rate'] ?? '5000000', // 5 Mbps is a standard bitrate for audio
            $data['nb_frames'] ?? '144000', // ~1h30m movie at 24fps
            Disposition::fromJson($data['disposition']),
            $data['tags']
        );
    }
}
