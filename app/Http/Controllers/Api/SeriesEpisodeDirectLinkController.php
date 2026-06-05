<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateSignedDirectLink;
use App\Http\Controllers\Api\Concerns\ResolvesSeriesEpisodes;
use App\Http\Controllers\Controller;
use App\Http\Integrations\LionzTv\Requests\GetSeriesInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Http\Resources\Api\ApiActionResource;
use App\Models\Series;

final class SeriesEpisodeDirectLinkController extends Controller
{
    use ResolvesSeriesEpisodes;

    public function show(XtreamCodesConnector $client, Series $series, int $season, int $episode): ApiActionResource
    {
        if (! config('features.direct_download_links', false)) {
            abort(404);
        }

        $dto = $client->send(new GetSeriesInfoRequest($series->series_id))->dtoOrFail();
        $selectedEpisode = $this->episodeOrFail($dto, $season, $episode);

        return new ApiActionResource([
            'id' => "series-direct:{$series->series_id}:{$selectedEpisode->id}",
            'type' => 'direct-links',
            'attributes' => [
                'url' => CreateSignedDirectLink::run($selectedEpisode),
                'expires_in_seconds' => 14_400,
                'series_id' => $series->series_id,
                'season' => $selectedEpisode->season,
                'episode' => $selectedEpisode->episodeNum,
                'episode_id' => $selectedEpisode->id,
            ],
        ]);
    }
}
