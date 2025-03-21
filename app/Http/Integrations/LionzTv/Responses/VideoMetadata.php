<?php

declare(strict_types=1);

namespace App\Http\Integrations\LionzTv\Responses;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Spatie\TypeScriptTransformer\Attributes\TypeScriptType;

#[TypeScript]
final readonly class VideoMetadata
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
        public int $width,
        public int $height,
        public int $codedWidth,
        public int $codedHeight,
        public int $hasBFrames,
        public string $pixFmt,
        public int $level,
        public string $chromaLocation,
        public int $refs,
        public string $isAvc,
        public string $nalLengthSize,
        public string $rFrameRate,
        public string $avgFrameRate,
        public string $timeBase,
        public int $startPts,
        public string $startTime,
        public int $durationTs,
        public string $duration,
        public string $bitRate,
        public string $bitsPerRawSample,
        public string $nbFrames,
        public Disposition $disposition,
        /** @var array{HANDLER_NAME:string,DURATION:string} */
        #[TypeScriptType(['HANDLER_NAME' => 'string', 'DURATION' => 'string'])]
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
            $data['chroma_location'] ?? 'left',
            $data['refs'],
            $data['is_avc'] ?? '0',
            $data['nal_length_size'] ?? '0',
            $data['r_frame_rate'],
            $data['avg_frame_rate'],
            $data['time_base'],
            $data['start_pts'],
            $data['start_time'],
            $data['duration_ts'] ?? 0,
            $data['duration'] ?? '0:00:00.000',
            $data['bit_rate'] ?? '5000000', // 5 Mbps is a reasonable bitrate for H264 HD content
            $data['bits_per_raw_sample'] ?? '8', // 8-bit color depth is standard for most H264 content
            $data['nb_frames'] ?? '144000', // ~1h30m movie at 24fps
            Disposition::fromJson($data['disposition']),
            $data['tags']
        );
    }
}
