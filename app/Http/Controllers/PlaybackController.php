<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class PlaybackController extends Controller
{
    public function show(string $mediaType, string $token): RedirectResponse
    {
        $remoteUrl = Cache::get("playback:link:{$mediaType}:{$token}");

        if (! is_string($remoteUrl) || $remoteUrl === '') {
            Log::info('Playback link expired or not found', [
                'media_type' => $mediaType,
                'token' => $token,
                'result' => 'miss',
            ]);

            abort(404);
        }

        Log::info('Playback link resolved', [
            'media_type' => $mediaType,
            'token' => $token,
            'result' => 'hit',
        ]);

        return redirect()->away($remoteUrl);
    }
}
