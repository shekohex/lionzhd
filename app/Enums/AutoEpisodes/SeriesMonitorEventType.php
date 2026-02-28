<?php

declare(strict_types=1);

namespace App\Enums\AutoEpisodes;

enum SeriesMonitorEventType: string
{
    case Queued = 'queued';
    case Duplicate = 'duplicate';
    case Deferred = 'deferred';
    case Skipped = 'skipped';
    case Error = 'error';
}
