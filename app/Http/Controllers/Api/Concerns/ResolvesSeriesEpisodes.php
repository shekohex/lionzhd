<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Concerns;

use App\Http\Integrations\LionzTv\Responses\Episode;
use App\Http\Integrations\LionzTv\Responses\SeriesInformation;
use App\Support\JsonApiErrorResponse;
use Illuminate\Http\Exceptions\HttpResponseException;

trait ResolvesSeriesEpisodes
{
    protected function episodeOrFail(SeriesInformation $series, int $season, int $episode): Episode
    {
        /** @var ?Episode $selectedEpisode */
        $selectedEpisode = $series->seasonsWithEpisodes[$season][$episode] ?? null;

        if (! $selectedEpisode instanceof Episode) {
            throw new HttpResponseException(JsonApiErrorResponse::make(404, 'Episode not found.'));
        }

        return $selectedEpisode;
    }

    /**
     * @param  list<array{season: int, episode: int}>  $episodes
     * @return list<Episode>
     */
    protected function selectedEpisodesOrFail(SeriesInformation $series, array $episodes): array
    {
        $selectedEpisodes = [];

        foreach ($episodes as $episode) {
            $selectedEpisodes[] = $this->episodeOrFail($series, $episode['season'], $episode['episode']);
        }

        return $selectedEpisodes;
    }
}
