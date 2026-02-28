<?php

declare(strict_types=1);

namespace App\Http\Controllers\AutoEpisodes;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AutoEpisodesPauseController extends Controller
{
    public function update(Request $request, #[CurrentUser] User $user): RedirectResponse
    {
        $validated = $request->validate([
            'paused' => ['required', 'boolean'],
        ]);

        $paused = (bool) $validated['paused'];

        $user->forceFill([
            'auto_episodes_paused_at' => $paused ? now()->toImmutable() : null,
        ])->save();

        return back()->with('success', $paused ? 'Auto-episodes monitoring paused.' : 'Auto-episodes monitoring resumed.');
    }
}
