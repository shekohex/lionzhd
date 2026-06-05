<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\BatchCreateSignedDirectLinks;
use App\Http\Controllers\Api\Concerns\ResolvesSeriesEpisodes;
use App\Http\Controllers\Controller;
use App\Http\Integrations\LionzTv\Requests\GetSeriesInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Http\Requests\Api\SeriesEpisodesSelectionRequest;
use App\Http\Resources\Api\ApiActionResource;
use App\Models\Series;

final class SeriesDirectLinksTextController extends Controller
{
    use ResolvesSeriesEpisodes;

    public function store(SeriesEpisodesSelectionRequest $request, XtreamCodesConnector $client, Series $series): ApiActionResource
    {
        if (! config('features.direct_download_links', false)) {
            abort(404);
        }

        $dto = $client->send(new GetSeriesInfoRequest($series->series_id))->dtoOrFail();
        $selectedEpisodes = $this->selectedEpisodesOrFail($dto, $request->selectedEpisodes());
        $signedUrls = BatchCreateSignedDirectLinks::run($selectedEpisodes);

        return new ApiActionResource([
            'id' => "series-direct-batch:{$series->series_id}",
            'type' => 'direct-links',
            'attributes' => [
                'urls' => $signedUrls->values()->all(),
                'text' => $signedUrls->implode(PHP_EOL).PHP_EOL,
                'expires_in_seconds' => 14_400,
                'series_id' => $series->series_id,
                'count' => $signedUrls->count(),
            ],
        ]);
    }
}
