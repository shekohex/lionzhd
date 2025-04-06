<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Data\XtreamCodesConfigData;
use App\Http\Controllers\Controller;
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
        return Inertia::render('settings/xtreamcodes', XtreamCodesConfigData::from($config));
    }

    /**
     * Update the user's Xtream Codes settings.
     */
    public function update(XtreamCodesConfigData $data, XtreamCodesConfig $config): RedirectResponse
    {
        $config->update($data->toArray());

        return back()->with('success', 'Xtream Codes settings updated successfully.');
    }
}
