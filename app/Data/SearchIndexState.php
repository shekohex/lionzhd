<?php

declare(strict_types=1);

namespace App\Data;

final readonly class SearchIndexState
{
    /**
     * @param  list<string>  $fields
     * @param  array<string, mixed>|null  $sample
     * @param  array<string, mixed>  $settings
     */
    public function __construct(
        public bool $exists,
        public int $documents,
        public array $fields,
        public ?array $sample,
        public array $settings = [],
    ) {}
}
