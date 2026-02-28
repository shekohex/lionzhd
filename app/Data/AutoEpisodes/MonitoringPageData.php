<?php

declare(strict_types=1);

namespace App\Data\AutoEpisodes;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class MonitoringPageData extends Data
{
    public function __construct(
        public bool $can_manage_schedules,
        public bool $is_paused,
        public ?CarbonImmutable $auto_episodes_paused_at,
        #[LiteralTypeScriptType('App.Data.AutoEpisodes.SeriesMonitorData[]')]
        public array $monitors,
        #[LiteralTypeScriptType('App.Data.AutoEpisodes.SeriesMonitorEventData[]')]
        public array $events,
        #[LiteralTypeScriptType('string[]')]
        public array $preset_times,
        #[LiteralTypeScriptType('number[]')]
        public array $backfill_preset_counts,
        public int $run_now_cooldown_seconds,
    ) {}
}
