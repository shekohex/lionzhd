<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\AutoEpisodes\ApplySeriesMonitoringPreset;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BulkApplySchedulesRequest;
use App\Http\Resources\Api\SettingsResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Facades\Gate;

final class SettingsSchedulesBulkApplyController extends Controller
{
    public function update(BulkApplySchedulesRequest $request, #[CurrentUser] User $user, ApplySeriesMonitoringPreset $applySeriesMonitoringPreset, SettingsSchedulesController $settingsSchedulesController): SettingsResource
    {
        Gate::authorize('auto-download-schedules');

        $validated = $request->validated();
        $seriesIds = array_values(array_unique(array_map(static fn (mixed $id): int => (int) $id, $validated['series_ids'])));
        $applySeriesMonitoringPreset->handle($user, $seriesIds, (string) $validated['preset']);

        return $settingsSchedulesController->index($user);
    }
}
