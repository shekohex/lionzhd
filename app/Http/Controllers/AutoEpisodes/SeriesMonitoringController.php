<?php

declare(strict_types=1);

namespace App\Http\Controllers\AutoEpisodes;

use App\Actions\AutoEpisodes\ManageSeriesMonitoring;
use App\Http\Controllers\Controller;
use App\Http\Requests\AutoEpisodes\StoreSeriesMonitorRequest;
use App\Http\Requests\AutoEpisodes\UpdateSeriesMonitorRequest;
use App\Models\Series;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SeriesMonitoringController extends Controller
{
    public function store(StoreSeriesMonitorRequest $request, #[CurrentUser] User $user, Series $model): RedirectResponse
    {
        ManageSeriesMonitoring::make()->store($user, $model, $request->validated());

        return back()->with('success', 'Series monitoring enabled.');
    }

    public function update(UpdateSeriesMonitorRequest $request, #[CurrentUser] User $user, Series $model): RedirectResponse
    {
        ManageSeriesMonitoring::make()->update($user, $model, $request->validated());

        return back()->with('success', 'Series monitoring updated.');
    }

    public function destroy(Request $request, #[CurrentUser] User $user, Series $model): RedirectResponse
    {
        $validated = $request->validate([
            'remove_from_watchlist' => ['sometimes', 'boolean'],
        ]);

        $removeFromWatchlist = (bool) ($validated['remove_from_watchlist'] ?? false);

        ManageSeriesMonitoring::make()->disable($user, $model, $removeFromWatchlist, requireExisting: false);

        return back()->with('success', 'Series monitoring disabled.');
    }
}
