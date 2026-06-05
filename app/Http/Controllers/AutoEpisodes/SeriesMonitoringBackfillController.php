<?php

declare(strict_types=1);

namespace App\Http\Controllers\AutoEpisodes;

use App\Actions\AutoEpisodes\ManageSeriesMonitoring;
use App\Http\Controllers\Controller;
use App\Http\Requests\AutoEpisodes\BackfillSeriesMonitorRequest;
use App\Models\Series;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final class SeriesMonitoringBackfillController extends Controller
{
    public function store(BackfillSeriesMonitorRequest $request, #[CurrentUser] User $user, Series $model): RedirectResponse
    {
        ManageSeriesMonitoring::make()->backfill($user, $model, $request->integer('backfill_count'));

        return back()->with('success', 'Series monitoring backfill queued.');
    }
}
