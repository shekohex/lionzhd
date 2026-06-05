<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Data\AutoEpisodes\SeriesMonitorData;
use App\Models\AutoEpisodes\SeriesMonitor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

final class SeriesMonitorResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->monitor()->getKey();
    }

    public function toType(Request $request): string
    {
        return 'series-monitors';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return SeriesMonitorData::fromModel($this->monitor())->toArray();
    }

    private function monitor(): SeriesMonitor
    {
        /** @var SeriesMonitor $monitor */
        $monitor = $this->resource;

        return $monitor;
    }
}
