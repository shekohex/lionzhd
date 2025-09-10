<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final class DirectDownloadController extends Controller
{
    /**
     * Resolve a signed direct download link and redirect to the remote URL.
     */
    public function show(Request $request, string $token): RedirectResponse|Response
    {
        if (! config('features.direct_download_links', false)) {
            abort(404);
        }
        $cacheKey = "direct:link:{$token}";
        $remoteUrl = Cache::get($cacheKey);

        if (! $remoteUrl) {
            Log::info('Direct download link expired or not found', [
                'token' => $token,
                'result' => 'miss',
            ]);

            abort(404);
        }

        Log::info('Direct download link resolved', [
            'token' => $token,
            'result' => 'hit',
        ]);

        // If this was initiated via an Inertia XHR visit, instruct the client
        // to perform a full browser navigation to the upstream URL.
        if ($request->headers->has('X-Inertia')) {
            return Inertia::location($remoteUrl);
        }

        // Normal top-level navigation: let the browser follow the 302 away.
        return redirect()->away($remoteUrl);
    }
}
