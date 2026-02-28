<?php

declare(strict_types=1);

namespace App\Jobs\AutoEpisodes;

use App\Actions\AutoEpisodes\ScanSeriesForNewEpisodes;
use App\Enums\AutoEpisodes\SeriesMonitorRunTrigger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

final class RunMonitorScan implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $monitorId,
        public readonly SeriesMonitorRunTrigger $trigger = SeriesMonitorRunTrigger::Scheduled,
        public readonly array $options = [],
    ) {}

    public function handle(): void
    {
        $lock = Cache::lock(self::lockKey($this->monitorId), 300);

        if (! $lock->get()) {
            return;
        }

        try {
            ScanSeriesForNewEpisodes::run(
                monitorId: $this->monitorId,
                trigger: $this->trigger,
                options: $this->options,
            );
        } finally {
            if ($lock->owner() !== null) {
                $lock->release();
            }
        }
    }

    public static function lockKey(int $monitorId): string
    {
        return sprintf('auto:episodes:monitor:%d', $monitorId);
    }
}
