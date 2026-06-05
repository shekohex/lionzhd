<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\QueueVodStreamDownload;
use App\Http\Controllers\Controller;
use App\Http\Integrations\LionzTv\Requests\GetVodInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Http\Resources\Api\ApiActionResource;
use App\Models\User;
use App\Models\VodStream;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Facades\Gate;

final class MovieDownloadController extends Controller
{
    public function store(#[CurrentUser] User $user, XtreamCodesConnector $client, VodStream $movie): ApiActionResource
    {
        Gate::authorize('server-download');

        $dto = $client->send(new GetVodInfoRequest($movie->stream_id))->dtoOrFail();
        $queuedDownload = QueueVodStreamDownload::run($user, $movie, $dto);

        if ($queuedDownload['preparing']) {
            abort(409, 'Download is already being prepared. Please try again.');
        }

        unset($queuedDownload['preparing']);

        return new ApiActionResource([
            'id' => "movie-download:{$movie->stream_id}",
            'type' => 'download-requests',
            'attributes' => $queuedDownload,
        ]);
    }
}
