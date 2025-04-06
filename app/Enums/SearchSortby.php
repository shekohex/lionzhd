<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum SearchSortby: string
{
    case Popular = 'popular';
    case Latest = 'latest';
    case Rating = 'rating';
}
