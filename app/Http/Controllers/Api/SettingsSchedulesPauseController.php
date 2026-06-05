<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PauseSchedulesRequest;
use App\Http\Resources\Api\SettingsResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Facades\Gate;

final class SettingsSchedulesPauseController extends Controller
{
    public function update(PauseSchedulesRequest $request, #[CurrentUser] User $user, SettingsSchedulesController $settingsSchedulesController): SettingsResource
    {
        Gate::authorize('auto-download-schedules');

        $user->forceFill(['auto_episodes_paused_at' => ((bool) $request->validated('paused')) ? now()->toImmutable() : null])->save();

        return $settingsSchedulesController->index($user->refresh());
    }
}
