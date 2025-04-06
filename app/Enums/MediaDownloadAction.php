<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum MediaDownloadAction: string
{
    case Pause = 'pause';
    case Resume = 'resume';
    case Cancel = 'cancel';
    case Remove = 'remove';
    case Retry = 'retry';

    public function isPause(): bool
    {
        return $this === self::Pause;
    }

    public function isResume(): bool
    {
        return $this === self::Resume;
    }

    public function isCancel(): bool
    {
        return $this === self::Cancel;
    }

    public function isRemove(): bool
    {
        return $this === self::Remove;
    }

    public function isRetry(): bool
    {
        return $this === self::Retry;
    }
}
