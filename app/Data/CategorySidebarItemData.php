<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class CategorySidebarItemData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $disabled,
        public bool $canNavigate,
        public bool $canEdit,
        public bool $isPinned,
        public bool $isHidden,
        public ?int $pinRank,
        public ?int $sortOrder,
        public bool $isUncategorized,
    ) {}
}
