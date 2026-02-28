<?php

declare(strict_types=1);

namespace App\Enums\AutoEpisodes;

enum SeriesMonitorRunStatus: string
{
    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';
    case SuccessWithWarnings = 'success_with_warnings';
}
