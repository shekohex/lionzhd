<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\DiscoverResource;
use App\Models\Series;
use App\Models\VodStream;

final class DiscoverController extends Controller
{
    public function show(): DiscoverResource
    {
        return new DiscoverResource([
            'movies' => VodStream::query()
                ->orderByDesc('added')
                ->limit(15)
                ->get(),
            'series' => Series::query()
                ->orderByDesc('last_modified')
                ->limit(15)
                ->get(),
        ]);
    }
}
