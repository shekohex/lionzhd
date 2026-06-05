<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateAria2SettingsRequest;
use App\Http\Resources\Api\SettingsResource;
use App\Models\Aria2Config;
use Illuminate\Support\Facades\Gate;

final class SettingsAria2Controller extends Controller
{
    public function show(): SettingsResource
    {
        Gate::authorize('admin');

        return $this->resource();
    }

    public function update(UpdateAria2SettingsRequest $request): SettingsResource
    {
        Gate::authorize('admin');

        $config = Aria2Config::query()->first() ?? app(Aria2Config::class);
        $config->forceFill(array_merge($config->only(['host', 'port', 'secret', 'use_ssl']), $request->validated()))->save();

        return $this->resource();
    }

    private function resource(): SettingsResource
    {
        $config = app(Aria2Config::class);

        return new SettingsResource(['id' => 'aria2', 'type' => 'settings', 'attributes' => [
            'host' => $config->host,
            'port' => $config->port,
            'use_ssl' => $config->use_ssl,
            'secret_configured' => filled($config->secret),
        ]]);
    }
}
