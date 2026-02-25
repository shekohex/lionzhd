<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum UserSubtype: string
{
    case Internal = 'internal';
    case External = 'external';
}
