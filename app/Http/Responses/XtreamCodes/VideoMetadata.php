<?php

namespace App\Http\Responses\XtreamCodes;

final readonly class VideoMetadata
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
        public readonly int $width,
        public readonly int $height,
        public readonly int $codedWidth,
        public readonly int $codedHeight,
        public readonly int $hasBFrames,
        public readonly string $pixFmt,
        public readonly int $level,
        public readonly string $chromaLocation,
        public readonly int $refs,
        public readonly string $isAvc,
        public readonly string $nalLengthSize,
        public readonly string $rFrameRate,
        public readonly string $avgFrameRate,
        public readonly string $timeBase,
        public readonly int $startPts,
        public readonly string $startTime,
        public readonly int $durationTs,
        public readonly string $duration,
        public readonly string $bitRate,
        public readonly string $bitsPerRawSample,
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
            $data['width'],
            $data['height'],
            $data['coded_width'],
            $data['coded_height'],
            $data['has_b_frames'],
            $data['pix_fmt'],
            $data['level'],
            $data['chroma_location'],
            $data['refs'],
            $data['is_avc'],
            $data['nal_length_size'],
            $data['r_frame_rate'],
            $data['avg_frame_rate'],
            $data['time_base'],
            $data['start_pts'],
            $data['start_time'],
            $data['duration_ts'],
            $data['duration'],
            $data['bit_rate'],
            $data['bits_per_raw_sample'],
            $data['nb_frames'],
            Disposition::fromJson($data['disposition']),
            $data['tags']
        );
    }
}
