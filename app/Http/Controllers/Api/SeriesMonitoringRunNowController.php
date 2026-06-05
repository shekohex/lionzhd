<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\AutoEpisodes\SeriesMonitorRunTrigger;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\SeriesMonitorResource;
use App\Jobs\AutoEpisodes\RunMonitorScan;
use App\Models\AutoEpisodes\SeriesMonitor;
use App\Models\Series;
use App\Models\User;
use App\Support\JsonApiErrorResponse;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;

final class SeriesMonitoringRunNowController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, Series $series): SeriesMonitorResource
    {
        Gate::authorize('auto-download-schedules');

        $monitor = $this->enabledMonitorForAction($user, $series, 'Enable monitoring before running this series now.');

        if ($monitor->run_now_available_at !== null && $monitor->run_now_available_at->isFuture()) {
            throw new HttpResponseException(JsonApiErrorResponse::make(422, sprintf('Run now is cooling down until %s.', $monitor->run_now_available_at->toDateTimeString()), 'run_now'));
        }

        RunMonitorScan::dispatch($monitor->id, SeriesMonitorRunTrigger::Manual);

        $monitor->forceFill([
            'run_now_available_at' => now()->toImmutable()->addSeconds(max(0, (int) config('auto_episodes.run_now_cooldown_seconds', 300))),
        ])->save();

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
