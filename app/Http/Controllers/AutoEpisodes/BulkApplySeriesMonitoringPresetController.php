<?php

declare(strict_types=1);

namespace App\Http\Controllers\AutoEpisodes;

use App\Actions\AutoEpisodes\ApplySeriesMonitoringPreset;
use App\Http\Controllers\Controller;
use App\Http\Requests\AutoEpisodes\BulkUpdateSeriesMonitorsRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final class BulkApplySeriesMonitoringPresetController extends Controller
{
    public function __invoke(BulkUpdateSeriesMonitorsRequest $request, #[CurrentUser] User $user, ApplySeriesMonitoringPreset $applySeriesMonitoringPreset): RedirectResponse
    {
        $validated = $request->validated();
        $seriesIds = array_values(array_unique(array_map(static fn (mixed $id): int => (int) $id, $validated['series_ids'])));
        $count = $applySeriesMonitoringPreset->handle($user, $seriesIds, (string) $validated['preset']);

        return back()->with('success', sprintf('Applied schedule preset to %d series monitor(s).', $count));
    }
}
