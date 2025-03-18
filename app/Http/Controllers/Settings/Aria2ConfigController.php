<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateAria2ConfigRequest;
use App\Models\Aria2Config;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class Aria2ConfigController extends Controller
{
    /**
     * Show the user's Aria2 settings page.
     */
    public function edit(Request $request, Aria2Config $config): Response
    {
        return Inertia::render('settings/aria2', [
            'host' => $config->host,
            'port' => $config->port,
            'secret' => $config->secret,
            'use_ssl' => $config->use_ssl,
        ]);
    }

    /**
     * Update the user's Aria2 settings.
     */
    public function update(UpdateAria2ConfigRequest $request, Aria2Config $config): RedirectResponse
    {
        $validated = $request->validated();

        $config->update($validated);

        return back()->with('success', 'Aria2 settings updated successfully.');
    }
}
