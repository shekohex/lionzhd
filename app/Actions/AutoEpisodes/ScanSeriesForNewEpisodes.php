<?php

declare(strict_types=1);

namespace App\Actions\AutoEpisodes;

use App\Concerns\AsAction;
use App\Models\AutoEpisodes\SeriesMonitor;

final readonly class ScanSeriesForNewEpisodes
{
    use AsAction;

    public function handle(int $monitorId): void
    {
        $exists = SeriesMonitor::query()->whereKey($monitorId)->exists();

        if (! $exists) {
            return;
        }
    }
}
