<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Data\Aria2ConfigData;
use App\Http\Controllers\Controller;
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
        return Inertia::render('settings/aria2', Aria2ConfigData::from($config));
    }

    /**
     * Update the user's Aria2 settings.
     */
    public function update(Aria2ConfigData $data, Aria2Config $config): RedirectResponse
    {

        $config->update($data->toArray());

        return back()->with('success', 'Aria2 settings updated successfully.');
    }
}
