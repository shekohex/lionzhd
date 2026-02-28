<?php

declare(strict_types=1);

namespace App\Data\AutoEpisodes;

use App\Enums\AutoEpisodes\SeriesMonitorEventType;
use App\Models\AutoEpisodes\SeriesMonitorEvent as SeriesMonitorEventModel;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class SeriesMonitorEventData extends Data
{
    public function __construct(
        public int $id,
        public int $monitor_id,
        public ?int $series_id,
        public ?string $series_name,
        public ?string $series_cover,
        public SeriesMonitorEventType $type,
        public ?string $reason,
        public ?string $episode_id,
        public ?int $season,
        public ?int $episode_num,
        public ?CarbonImmutable $created_at,
    ) {}

    public static function fromModel(SeriesMonitorEventModel $event): self
    {
        $type = $event->type;

        if (! $type instanceof SeriesMonitorEventType) {
            $type = SeriesMonitorEventType::Error;
        }

        return new self(
            id: $event->id,
            monitor_id: (int) $event->monitor_id,
            series_id: $event->monitor?->series_id,
            series_name: $event->monitor?->series?->name,
            series_cover: $event->monitor?->series?->cover,
            type: $type,
            reason: $event->reason,
            episode_id: $event->episode_id,
            season: $event->season,
            episode_num: $event->episode_num,
            created_at: $event->created_at?->toImmutable(),
        );
    }
}
