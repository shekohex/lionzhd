<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateXtreamCodesSettingsRequest;
use App\Http\Resources\Api\SettingsResource;
use App\Models\XtreamCodesConfig;
use Illuminate\Support\Facades\Gate;

final class SettingsXtreamCodesController extends Controller
{
    public function show(): SettingsResource
    {
        Gate::authorize('admin');

        return $this->resource();
    }

    public function update(UpdateXtreamCodesSettingsRequest $request): SettingsResource
    {
        Gate::authorize('admin');

        $config = XtreamCodesConfig::query()->first() ?? app(XtreamCodesConfig::class);
        $config->forceFill(array_merge($config->only(['host', 'port', 'username', 'password']), $request->validated()))->save();

        return $this->resource();
    }

    private function resource(): SettingsResource
    {
        $config = app(XtreamCodesConfig::class);

        return new SettingsResource(['id' => 'xtreamcodes', 'type' => 'settings', 'attributes' => [
            'host' => $config->host,
            'port' => $config->port,
            'username' => $config->username,
            'password_configured' => filled($config->password),
        ]]);
    }
}
