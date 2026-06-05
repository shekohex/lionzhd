<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\InvalidateVodStreamCache;
use App\Http\Controllers\Controller;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Http\Resources\Api\ApiActionResource;
use App\Models\VodStream;
use Illuminate\Support\Facades\Gate;

final class MovieCacheController extends Controller
{
    public function destroy(XtreamCodesConnector $client, VodStream $movie): ApiActionResource
    {
        Gate::authorize('admin');

        InvalidateVodStreamCache::run($client, $movie);

        return new ApiActionResource([
            'id' => "movie-cache:{$movie->stream_id}",
            'type' => 'cache-invalidations',
            'attributes' => [
                'status' => 'invalidated',
            ],
        ]);
    }
}
