<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class CategoryBrowseRecoveryStateData extends Data
{
    public function __construct(
        public bool $allCategoriesEmptyDueToIgnored,
        public bool $allCategoriesEmptyDueToHidden,
    ) {}
}
