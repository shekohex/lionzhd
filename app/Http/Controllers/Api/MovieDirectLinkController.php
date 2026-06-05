<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateSignedDirectLink;
use App\Http\Controllers\Controller;
use App\Http\Integrations\LionzTv\Requests\GetVodInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Http\Resources\Api\ApiActionResource;
use App\Models\VodStream;

final class MovieDirectLinkController extends Controller
{
    public function show(XtreamCodesConnector $client, VodStream $movie): ApiActionResource
    {
        if (! config('features.direct_download_links', false)) {
            abort(404);
        }

        return new ApiActionResource([
            'id' => "movie-direct:{$movie->stream_id}",
            'type' => 'direct-links',
            'attributes' => [
                'url' => CreateSignedDirectLink::run($client->send(new GetVodInfoRequest($movie->stream_id))->dtoOrFail()),
                'expires_in_seconds' => 14_400,
            ],
        ]);
    }
}
