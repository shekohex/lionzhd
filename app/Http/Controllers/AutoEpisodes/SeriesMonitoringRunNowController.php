<?php

declare(strict_types=1);

namespace App\Http\Controllers\AutoEpisodes;

use App\Enums\AutoEpisodes\SeriesMonitorRunTrigger;
use App\Http\Controllers\Controller;
use App\Jobs\AutoEpisodes\RunMonitorScan;
use App\Models\AutoEpisodes\SeriesMonitor;
use App\Models\Series;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final class SeriesMonitoringRunNowController extends Controller
{
    public function store(#[CurrentUser] User $user, Series $model): RedirectResponse
    {
        $monitor = SeriesMonitor::query()
            ->where('user_id', $user->id)
            ->where('series_id', $model->series_id)
            ->first();

        if (! $monitor instanceof SeriesMonitor || ! $monitor->enabled) {
            throw ValidationException::withMessages([
                'series' => 'Enable monitoring before running this series now.',
            ]);
        }

        $now = now()->toImmutable();

        if ($monitor->run_now_available_at !== null && $monitor->run_now_available_at->isFuture()) {
            throw ValidationException::withMessages([
                'run_now' => sprintf('Run now is cooling down until %s.', $monitor->run_now_available_at->toDateTimeString()),
            ]);
        }

        RunMonitorScan::dispatch($monitor->id, SeriesMonitorRunTrigger::Manual);

        $cooldownSeconds = max(0, (int) config('auto_episodes.run_now_cooldown_seconds', 300));
        $monitor->forceFill([
            'run_now_available_at' => $now->addSeconds($cooldownSeconds),
        ])->save();

        return back()->with('success', 'Series monitoring run queued.');
    }
}
