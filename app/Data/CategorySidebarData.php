<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class CategorySidebarData extends Data
{
    public function __construct(
        /** @var DataCollection<CategorySidebarItemData> */
        #[DataCollectionOf(CategorySidebarItemData::class)]
        public DataCollection $visibleItems,
        /** @var DataCollection<CategorySidebarItemData> */
        #[DataCollectionOf(CategorySidebarItemData::class)]
        public DataCollection $hiddenItems,
        public bool $selectedCategoryIsHidden,
        public ?string $selectedCategoryName,
        public int $pinLimit,
        public bool $canReset,
    ) {}
}
