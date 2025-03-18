<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateXtreamCodeConfigRequest;
use App\Models\XtreamCodesConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class XtreamCodeConfigController extends Controller
{
    /**
     * Show the user's Xtream Codes settings page.
     */
    public function edit(Request $request, XtreamCodesConfig $config): Response
    {
        return Inertia::render('settings/xtreamcodes', [
            'host' => $config->host,
            'port' => $config->port,
            'username' => $config->username,
            'password' => $config->password,
        ]);
    }

    /**
     * Update the user's Xtream Codes settings.
     */
    public function update(UpdateXtreamCodeConfigRequest $request, XtreamCodesConfig $config): RedirectResponse
    {
        $validated = $request->validated();

        $config->update($validated);

        return back()->with('success', 'Xtream Codes settings updated successfully.');
    }
}
