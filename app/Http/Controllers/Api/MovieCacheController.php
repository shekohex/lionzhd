<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Integrations\LionzTv\Requests\GetVodInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Http\Resources\Api\ApiActionResource;
use App\Models\VodStream;

final class MovieCacheController extends Controller
{
    public function destroy(XtreamCodesConnector $client, VodStream $movie): ApiActionResource
    {
        $client->send((new GetVodInfoRequest($movie->stream_id))->invalidateCache());

        return new ApiActionResource([
            'id' => "movie-cache:{$movie->stream_id}",
            'type' => 'cache-invalidations',
            'attributes' => [
                'status' => 'invalidated',
            ],
        ]);
    }
}
