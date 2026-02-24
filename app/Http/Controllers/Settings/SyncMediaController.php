<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Jobs\RefreshMediaContents;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SyncMediaController extends Controller
{
    /**
     * Show the Sync Media settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/syncmedia');
    }

    /**
     * Sync Media.
     */
    public function update(): RedirectResponse
    {
        RefreshMediaContents::dispatch();

        return back()->with('success', 'Media Library sync queued successfully.');
    }
}
