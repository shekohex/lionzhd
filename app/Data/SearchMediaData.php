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
        public ?int $per_page,
        #[Min(1)]
        public ?int $page = 1,
        public ?MediaType $media_type = null,
        public ?SearchSortby $sort_by = SearchSortby::Latest,
    ) {}

    public function isMovie(): bool
    {
        return $this->media_type?->isMovie() ?? false;
    }

    public function isSeries(): bool
    {
        return $this->media_type?->isSeries() ?? false;
    }

    public function hasQuery(): bool
    {
        return $this->q !== null && $this->q !== '';
    }
}
