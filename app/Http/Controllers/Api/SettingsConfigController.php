<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\Aria2ConfigData;
use App\Data\XtreamCodesConfigData;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\SettingsResource;
use App\Models\Aria2Config;
use App\Models\XtreamCodesConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class SettingsConfigController extends Controller
{
    public function show(string $section): SettingsResource
    {
        Gate::authorize('admin');

        return $section === 'aria2' ? $this->aria2() : $this->xtreamcodes();
    }

    public function update(Request $request, string $section): SettingsResource
    {
        Gate::authorize('admin');

        if ($section === 'aria2') {
            app(Aria2Config::class)->update($request->validate([
                'host' => ['required', 'string'],
                'port' => ['required', 'integer', 'between:1,65535'],
                'secret' => ['required', 'string'],
                'use_ssl' => ['sometimes', 'boolean'],
            ]));

            return $this->aria2();
        }

        app(XtreamCodesConfig::class)->update($request->validate([
            'host' => ['required', 'string'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]));

        return $this->xtreamcodes();
    }

    private function aria2(): SettingsResource
    {
        return new SettingsResource(['id' => 'aria2', 'type' => 'settings', 'attributes' => Aria2ConfigData::from(app(Aria2Config::class))->toArray()]);
    }

    private function xtreamcodes(): SettingsResource
    {
        return new SettingsResource(['id' => 'xtreamcodes', 'type' => 'settings', 'attributes' => XtreamCodesConfigData::from(app(XtreamCodesConfig::class))->toArray()]);
    }
}
