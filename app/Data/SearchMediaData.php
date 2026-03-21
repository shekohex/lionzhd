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
    private const TOKEN_PATTERN = '/(?:^|\s)(type|sort):([^\s]+)/i';

    public function __construct(
        public ?string $q,
        #[Min(1)]
        public int $per_page = 10,
        #[Min(1)]
        public ?int $page = 1,
        public ?MediaType $media_type = null,
        public ?SearchSortby $sort_by = SearchSortby::Latest,
    ) {}

    public function normalizedQuery(): ?string
    {
        $query = preg_replace(self::TOKEN_PATTERN, ' ', $this->q ?? '');
        $query = preg_replace('/\s+/', ' ', $query ?? '');
        $query = trim($query ?? '');

        return $query === '' ? null : $query;
    }

    public function resolvedMediaType(): ?MediaType
    {
        if (request()->query->has('media_type')) {
            return $this->media_type;
        }

        return $this->parsedMediaType() ?? $this->media_type;
    }

    public function resolvedSortBy(): ?SearchSortby
    {
        if (request()->query->has('sort_by')) {
            return $this->sort_by;
        }

        return $this->parsedSortBy() ?? $this->sort_by;
    }

    public function normalized(): array
    {
        return [
            'q' => $this->normalizedQuery(),
            'media_type' => $this->resolvedMediaType(),
            'sort_by' => $this->resolvedSortBy(),
        ];
    }

    public function resolvedFilters(): self
    {
        return new self(
            q: $this->q,
            per_page: $this->per_page,
            page: $this->page,
            media_type: $this->resolvedMediaType(),
            sort_by: $this->resolvedSortBy(),
        );
    }

    public function isMovie(): bool
    {
        return $this->resolvedMediaType()?->isMovie() ?? false;
    }

    public function isSeries(): bool
    {
        return $this->resolvedMediaType()?->isSeries() ?? false;
    }

    public function hasQuery(): bool
    {
        return $this->normalizedQuery() !== null;
    }

    private function parsedMediaType(): ?MediaType
    {
        $parsed = $this->parsedTokens()['media_type'] ?? null;

        return $parsed instanceof MediaType ? $parsed : null;
    }

    private function parsedSortBy(): ?SearchSortby
    {
        $parsed = $this->parsedTokens()['sort_by'] ?? null;

        return $parsed instanceof SearchSortby ? $parsed : null;
    }

    private function parsedTokens(): array
    {
        preg_match_all(self::TOKEN_PATTERN, $this->q ?? '', $matches, PREG_SET_ORDER);

        $parsed = [
            'media_type' => null,
            'sort_by' => null,
        ];

        foreach ($matches as $match) {
            $filter = strtolower($match[1] ?? '');
            $value = strtolower($match[2] ?? '');

            if ($filter === 'type') {
                $parsed['media_type'] = MediaType::tryFrom($value);
            }

            if ($filter === 'sort') {
                $parsed['sort_by'] = SearchSortby::tryFrom($value);
            }
        }

        return $parsed;
    }
}
