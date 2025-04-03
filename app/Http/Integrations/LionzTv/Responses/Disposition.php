<?php

declare(strict_types=1);

namespace App\Http\Integrations\LionzTv\Responses;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
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
            $data['default'] ?? 0,
            $data['dub'] ?? 0,
            $data['original'] ?? 0,
            $data['comment'] ?? 0,
            $data['lyrics'] ?? 0,
            $data['karaoke'] ?? 0,
            $data['forced'] ?? 0,
            $data['hearing_impaired'] ?? 0,
            $data['visual_impaired'] ?? 0,
            $data['clean_effects'] ?? 0,
            $data['attached_pic'] ?? 0,
            $data['timed_thumbnails'] ?? 0
        );
    }
}
