<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\DiscoverResource;
use App\Models\Series;
use App\Models\User;
use App\Models\VodStream;
use Illuminate\Container\Attributes\CurrentUser;

final class DiscoverController extends Controller
{
    public function show(#[CurrentUser] User $user): DiscoverResource
    {
        return new DiscoverResource([
            'movies' => VodStream::query()
                ->withExists(['watchlists as in_watchlist' => static function ($query) use ($user): void {
                    $query->where('user_id', $user->id);
                }])
                ->orderByDesc('added')
                ->limit(15)
                ->get(),
            'series' => Series::query()
                ->withExists(['watchlists as in_watchlist' => static function ($query) use ($user): void {
                    $query->where('user_id', $user->id);
                }])
                ->orderByDesc('last_modified')
                ->limit(15)
                ->get(),
        ]);
    }
}
