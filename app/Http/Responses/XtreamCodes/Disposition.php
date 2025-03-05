<?php

namespace App\Http\Responses\XtreamCodes;

final readonly class Disposition
{
    public function __construct(
        public int $default,
        public int $dub,
        public int $original,
        public readonly int $comment,
        public readonly int $lyrics,
        public readonly int $karaoke,
        public readonly int $forced,
        public readonly int $hearingImpaired,
        public readonly int $visualImpaired,
        public readonly int $cleanEffects,
        public readonly int $attachedPic,
        public readonly int $timedThumbnails
    ) {}

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
