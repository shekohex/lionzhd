<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\MediaType;
use App\Enums\SearchSortby;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class SearchMediaData extends Data
{
    public function __construct(
        public ?string $q,
        #[Min(1)]
        public ?int $page = 1,
        public ?int $per_page = 5,
        public ?MediaType $media_type = null,
        public ?SearchSortby $sort_by = SearchSortby::Popular,
        public ?bool $lightweight = false,
    ) {}

    public function isMovie(): bool
    {
        return $this->media_type?->isMovie();
    }

    public function isSeries(): bool
    {
        return $this->media_type?->isSeries();
    }

    public function hasQuery(): bool
    {
        return $this->q !== null && $this->q !== '';
    }
}
