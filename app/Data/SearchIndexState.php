<?php

declare(strict_types=1);

namespace App\Data;

final readonly class SearchIndexState
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function __construct(
        public bool $exists,
        public int $documents,
        public ?string $fingerprint,
        public array $settings = [],
    ) {}
}
