<?php

declare(strict_types=1);

namespace App\Http\Controllers\AutoEpisodes;

use App\Enums\AutoEpisodes\SeriesMonitorRunTrigger;
use App\Http\Controllers\Controller;
use App\Http\Requests\AutoEpisodes\BackfillSeriesMonitorRequest;
use App\Jobs\AutoEpisodes\RunMonitorScan;
use App\Models\AutoEpisodes\SeriesMonitor;
use App\Models\Series;
use App\Models\User;
use App\Models\Watchlist;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final class SeriesMonitoringBackfillController extends Controller
{
    public function store(BackfillSeriesMonitorRequest $request, #[CurrentUser] User $user, Series $model): RedirectResponse
    {
        $watchlistExists = Watchlist::query()
            ->where('user_id', $user->id)
            ->where('watchable_type', Series::class)
            ->where('watchable_id', $model->series_id)
            ->exists();

        if (! $watchlistExists) {
            throw ValidationException::withMessages([
                'series' => 'Add this series to your watchlist before requesting backfill.',
            ]);
        }

        $monitor = SeriesMonitor::query()
            ->where('user_id', $user->id)
            ->where('series_id', $model->series_id)
            ->first();

        if (! $monitor instanceof SeriesMonitor || ! $monitor->enabled) {
            throw ValidationException::withMessages([
                'series' => 'Enable monitoring before requesting backfill for this series.',
            ]);
        }

        $backfillCount = $request->integer('backfill_count');

        RunMonitorScan::dispatch(
            $monitor->id,
            SeriesMonitorRunTrigger::Backfill,
            ['backfill_count' => $backfillCount],
        );

        return back()->with('success', 'Series monitoring backfill queued.');
    }
}
