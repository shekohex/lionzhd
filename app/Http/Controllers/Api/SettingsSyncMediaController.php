<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\SettingsResource;
use App\Jobs\RefreshMediaContents;
use Illuminate\Support\Facades\Gate;

final class SettingsSyncMediaController extends Controller
{
    public function show(): SettingsResource
    {
        Gate::authorize('admin');

        return new SettingsResource(['id' => 'sync-media', 'type' => 'settings', 'attributes' => ['status' => 'available']]);
    }

    public function update(): SettingsResource
    {
        Gate::authorize('admin');

        RefreshMediaContents::dispatch();

        return new SettingsResource(['id' => 'sync-media', 'type' => 'settings-actions', 'attributes' => ['status' => 'queued']]);
    }
}
