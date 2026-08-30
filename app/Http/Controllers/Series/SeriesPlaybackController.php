<?php

declare(strict_types=1);

namespace App\Http\Controllers\Series;

use App\Actions\CreateSignedPlaybackLink;
use App\Http\Controllers\Controller;
use App\Http\Integrations\LionzTv\Requests\GetSeriesInfoRequest;
use App\Http\Integrations\LionzTv\Responses\Episode;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\Series;
use Illuminate\Http\JsonResponse;

final class SeriesPlaybackController extends Controller
{
    public function show(
        XtreamCodesConnector $client,
        CreateSignedPlaybackLink $createSignedPlaybackLink,
        Series $model,
        int $season,
        int $episode,
    ): JsonResponse {
        $response = $client->send(new GetSeriesInfoRequest($model->series_id));

        if ($response->status() === 404) {
            abort(404);
        }

        /** @var Episode|null $selectedEpisode */
        $selectedEpisode = $response->dtoOrFail()->seasonsWithEpisodes[$season][$episode] ?? null;

        if ($selectedEpisode === null) {
            abort(404);
        }

        return response()->json([
            'url' => $createSignedPlaybackLink($selectedEpisode),
        ]);
    }
}
