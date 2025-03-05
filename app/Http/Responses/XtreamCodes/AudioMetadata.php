<?php

namespace App\Http\Responses\XtreamCodes;

final readonly class AudioMetadata
{
    /**
     * @param  array<string,string>  $tags
     */
    public function __construct(
        public readonly int $index,
        public readonly string $codecName,
        public readonly string $codecLongName,
        public readonly string $profile,
        public readonly string $codecType,
        public readonly string $codecTimeBase,
        public readonly string $codecTagString,
        public readonly string $codecTag,
        public readonly string $sampleFmt,
        public readonly string $sampleRate,
        public readonly int $channels,
        public readonly string $channelLayout,
        public readonly int $bitsPerSample,
        public readonly string $rFrameRate,
        public readonly string $avgFrameRate,
        public readonly string $timeBase,
        public readonly int $startPts,
        public readonly string $startTime,
        public readonly int $durationTs,
        public readonly string $duration,
        public readonly string $bitRate,
        public readonly string $maxBitRate,
        public readonly string $nbFrames,
        public readonly Disposition $disposition,
        public readonly array $tags
    ) {}

    public static function fromJson(array $data): self
    {
        return new self(
            $data['index'],
            $data['codec_name'],
            $data['codec_long_name'],
            $data['profile'],
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
            $data['duration_ts'],
            $data['duration'],
            $data['bit_rate'],
            $data['max_bit_rate'],
            $data['nb_frames'],
            Disposition::fromJson($data['disposition']),
            $data['tags']
        );
    }
}
