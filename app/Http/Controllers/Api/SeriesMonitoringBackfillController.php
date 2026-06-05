<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\AutoEpisodes\SeriesMonitorRunTrigger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BackfillSeriesMonitoringRequest;
use App\Http\Resources\Api\SeriesMonitorResource;
use App\Jobs\AutoEpisodes\RunMonitorScan;
use App\Models\AutoEpisodes\SeriesMonitor;
use App\Models\Series;
use App\Models\User;
use App\Models\Watchlist;
use App\Support\JsonApiErrorResponse;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;

final class SeriesMonitoringBackfillController extends Controller
{
    public function __invoke(BackfillSeriesMonitoringRequest $request, #[CurrentUser] User $user, Series $series): SeriesMonitorResource
    {
        Gate::authorize('auto-download-schedules');

        $watchlistExists = Watchlist::query()
            ->where('user_id', $user->id)
            ->where('watchable_type', Series::class)
            ->where('watchable_id', $series->series_id)
            ->exists();

        if (! $watchlistExists) {
            throw new HttpResponseException(JsonApiErrorResponse::make(422, 'Add this series to your watchlist before requesting backfill.', 'series'));
        }

        $monitor = $this->enabledMonitorForAction($user, $series, 'Enable monitoring before requesting backfill for this series.');

        RunMonitorScan::dispatch($monitor->id, SeriesMonitorRunTrigger::Backfill, ['backfill_count' => $request->integer('backfill_count')]);

        return new SeriesMonitorResource($monitor->refresh()->load('series'));
    }

    private function enabledMonitorForAction(User $user, Series $series, string $message): SeriesMonitor
    {
        $monitor = SeriesMonitor::query()
            ->where('user_id', $user->id)
            ->where('series_id', $series->series_id)
            ->first();

        if (! $monitor instanceof SeriesMonitor || ! $monitor->enabled) {
            throw new HttpResponseException(JsonApiErrorResponse::make(422, $message, 'series'));
        }

        return $monitor;
    }
}
