<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum CategorySyncRunStatus: string
{
    case Running = 'running';
    case Success = 'success';
    case SuccessWithWarnings = 'success_with_warnings';
    case Failed = 'failed';
}
