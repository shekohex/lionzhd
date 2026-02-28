<?php

declare(strict_types=1);

namespace App\Enums\AutoEpisodes;

enum SeriesMonitorRunTrigger: string
{
    case Scheduled = 'scheduled';
    case Manual = 'manual';
    case Backfill = 'backfill';
}
