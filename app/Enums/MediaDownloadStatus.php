<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum MediaDownloadStatus: string
{
    case UNKNOWN = 'unknown';
    case Active = 'active';
    case Waiting = 'waiting';
    case Paused = 'paused';
    case Error = 'error';
    case Complete = 'complete';
    case Removed = 'removed';

    /**
     * Check if the current status can take the given action.
     */
    public function canTakeAction(MediaDownloadAction $action): bool
    {
        return match ($this) {
            self::Active => match ($action) {
                MediaDownloadAction::Pause,
                MediaDownloadAction::Cancel,
                MediaDownloadAction::Remove => true,
                default => false,
            },
            self::Waiting => match ($action) {
                MediaDownloadAction::Cancel,
                MediaDownloadAction::Remove => true,
                default => false,
            },
            self::Paused => match ($action) {
                MediaDownloadAction::Resume,
                MediaDownloadAction::Cancel,
                MediaDownloadAction::Remove => true,
                default => false,
            },
            self::Error => match ($action) {
                MediaDownloadAction::Retry,
                MediaDownloadAction::Remove => true,
                default => false,
            },
            self::Complete => match ($action) {
                MediaDownloadAction::Remove => true,
                default => false,
            },
            self::Removed => false,
            self::UNKNOWN => false,
        };
    }

    public function downloadedOrDownloading(): bool
    {
        return match ($this) {
            self::Active,
            self::Waiting,
            self::Paused,
            self::Complete => true,
            default => false,
        };
    }

    public function isCompleted(): bool
    {
        return $this === self::Complete;
    }

    public function isError(): bool
    {
        return $this === self::Error;
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    public function isWaiting(): bool
    {
        return $this === self::Waiting;
    }

    public function isPaused(): bool
    {
        return $this === self::Paused;
    }

    public function isRemoved(): bool
    {
        return $this === self::Removed;
    }
}
