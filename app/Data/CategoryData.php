<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class CategoryData extends Data
{
    public int $category_id;

    public string $category_name;

    public int $parent_id;

    public string $type;
}
