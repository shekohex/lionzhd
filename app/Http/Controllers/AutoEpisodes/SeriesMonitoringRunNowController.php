<?php

declare(strict_types=1);

namespace App\Http\Controllers\AutoEpisodes;

use App\Actions\AutoEpisodes\ManageSeriesMonitoring;
use App\Http\Controllers\Controller;
use App\Models\Series;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final class SeriesMonitoringRunNowController extends Controller
{
    public function store(#[CurrentUser] User $user, Series $model): RedirectResponse
    {
        ManageSeriesMonitoring::make()->runNow($user, $model);

        return back()->with('success', 'Series monitoring run queued.');
    }
}
