<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Series;
use App\Models\VodStream;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class MediaDownloadRefData extends Data
{
    public function __construct(
        public int $id,
        public string $gid,
        public int $media_id,
        #[LiteralTypeScriptType('"movie"|"series"')]
        public string $media_type,
        public int $downloadable_id,
        public CarbonImmutable $created_at,
        public CarbonImmutable $updated_at,
        public VodStreamData|SeriesData $media,
        public ?MediaDownloadStatusData $downloadStatus = null,
        public ?int $episode = null,
    ) {
        match ($this->media_type) {
            VodStream::class => $this->media_type = 'movie',
            Series::class => $this->media_type = 'series',
            default => throw new InvalidArgumentException('Invalid media type'),
        };
    }

    public function withDownloadStatus(MediaDownloadStatusData $status): self
    {
        // Clone the current instance to avoid modifying the original
        $clone = clone $this;
        // Set the status property of the cloned instance
        $clone->downloadStatus = $status;

        return $clone;
    }
}
