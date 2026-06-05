<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\QueueSeriesEpisodeDownload;
use App\Http\Controllers\Api\Concerns\ResolvesSeriesEpisodes;
use App\Http\Controllers\Controller;
use App\Http\Integrations\LionzTv\Requests\GetSeriesInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Http\Resources\Api\ApiActionResource;
use App\Models\Series;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Facades\Gate;

final class SeriesEpisodeDownloadController extends Controller
{
    use ResolvesSeriesEpisodes;

    public function store(#[CurrentUser] User $user, XtreamCodesConnector $client, Series $series, int $season, int $episode): ApiActionResource
    {
        Gate::authorize('server-download');

        $dto = $client->send(new GetSeriesInfoRequest($series->series_id))->dtoOrFail();
        $selectedEpisode = $this->episodeOrFail($dto, $season, $episode);
        $queuedDownload = QueueSeriesEpisodeDownload::run($user, $series, $dto, $selectedEpisode);

        if ($queuedDownload['preparing']) {
            abort(409, 'Download is already being prepared. Please try again.');
        }

        unset($queuedDownload['preparing']);

        return new ApiActionResource([
            'id' => "series-download:{$series->series_id}:{$selectedEpisode->id}",
            'type' => 'download-requests',
            'attributes' => $queuedDownload + [
                'series_id' => $series->series_id,
                'season' => $selectedEpisode->season,
                'episode' => $selectedEpisode->episodeNum,
                'episode_id' => $selectedEpisode->id,
            ],
        ]);
    }
}
