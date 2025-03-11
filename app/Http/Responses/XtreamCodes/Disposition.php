<?php

declare(strict_types=1);

namespace App\Http\Responses\XtreamCodes;

final readonly class Disposition
{
    public function __construct(
        public int $default,
        public int $dub,
        public int $original,
        public int $comment,
        public int $lyrics,
        public int $karaoke,
        public int $forced,
        public int $hearingImpaired,
        public int $visualImpaired,
        public int $cleanEffects,
        public int $attachedPic,
        public int $timedThumbnails
    ) {}

    /**
     * @param  array<string,mixed>  $data
     */
    public static function fromJson(array $data): self
    {
        return new self(
            $data['default'],
            $data['dub'],
            $data['original'],
            $data['comment'],
            $data['lyrics'],
            $data['karaoke'],
            $data['forced'],
            $data['hearing_impaired'],
            $data['visual_impaired'],
            $data['clean_effects'],
            $data['attached_pic'],
            $data['timed_thumbnails']
        );
    }
}
