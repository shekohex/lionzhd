<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\BatchCreateSignedDirectLinks;
use App\Http\Controllers\Api\Concerns\ResolvesSeriesEpisodes;
use App\Http\Controllers\Controller;
use App\Http\Integrations\LionzTv\Requests\GetSeriesInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Http\Requests\Api\SeriesEpisodesSelectionRequest;
use App\Models\Series;
use Symfony\Component\HttpFoundation\Response;

final class SeriesDirectLinksTextController extends Controller
{
    use ResolvesSeriesEpisodes;

    public function store(SeriesEpisodesSelectionRequest $request, XtreamCodesConnector $client, Series $series): Response
    {
        if (! config('features.direct_download_links', false)) {
            abort(404);
        }

        $dto = $client->send(new GetSeriesInfoRequest($series->series_id))->dtoOrFail();
        $selectedEpisodes = $this->selectedEpisodesOrFail($dto, $request->selectedEpisodes());
        $signedUrls = BatchCreateSignedDirectLinks::run($selectedEpisodes);

        return new Response($signedUrls->implode(PHP_EOL).PHP_EOL, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
