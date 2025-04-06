<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\MediaDownloadAction;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class EditMediaDownloadData extends Data
{
    public function __construct(
        public readonly MediaDownloadAction $action,
    ) {}
}
