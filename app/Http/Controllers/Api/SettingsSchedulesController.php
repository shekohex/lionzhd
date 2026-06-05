<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\AutoEpisodes\SeriesMonitorData;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\SettingsResource;
use App\Models\AutoEpisodes\SeriesMonitor;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Facades\Gate;

final class SettingsSchedulesController extends Controller
{
    public function index(#[CurrentUser] User $user): SettingsResource
    {
        Gate::authorize('auto-download-schedules');

        return new SettingsResource(['id' => 'schedules', 'type' => 'settings', 'attributes' => [
            'is_paused' => $user->auto_episodes_paused_at !== null,
            'auto_episodes_paused_at' => $user->auto_episodes_paused_at,
            'monitors' => SeriesMonitor::query()->where('user_id', $user->id)->with(['series:series_id,name,cover'])->get()->map(static fn (SeriesMonitor $monitor): array => SeriesMonitorData::fromModel($monitor)->toArray())->values()->all(),
        ]]);
    }
}
