<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\BatchDownloadMedia;
use App\Actions\CreateDownloadOut;
use App\Actions\CreateXtreamcodesDownloadUrl;
use App\Http\Controllers\Api\Concerns\ResolvesSeriesEpisodes;
use App\Http\Controllers\Controller;
use App\Http\Integrations\LionzTv\Requests\GetSeriesInfoRequest;
use App\Http\Integrations\LionzTv\Responses\Episode;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Http\Requests\Api\SeriesEpisodesSelectionRequest;
use App\Http\Resources\Api\ApiActionResource;
use App\Models\MediaDownloadRef;
use App\Models\Series;
use App\Models\User;
use App\Support\JsonApiErrorResponse;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class SeriesDownloadController extends Controller
{
    use ResolvesSeriesEpisodes;

    private const int SAVE_ATTEMPTS = 5;

    public function store(SeriesEpisodesSelectionRequest $request, #[CurrentUser] User $user, XtreamCodesConnector $client, Series $series): ApiActionResource
    {
        Gate::authorize('server-download');

        $dto = $client->send(new GetSeriesInfoRequest($series->series_id))->dtoOrFail();
        $selectedEpisodes = $this->selectedEpisodesOrFail($dto, $request->selectedEpisodes());

        $urls = collect($selectedEpisodes)->map(fn (Episode $episode) => CreateXtreamcodesDownloadUrl::run($episode));
        $gids = BatchDownloadMedia::run($urls->toArray(), fn (int $index) => [
            'out' => CreateDownloadOut::run($dto, $selectedEpisodes[$index]),
        ]);

        $errors = $gids->filter(fn (mixed $response): bool => is_array($response) && isset($response['error']))->map(fn (array $response): mixed => $response['error']);

        if ($errors->isNotEmpty()) {
            throw new HttpResponseException(JsonApiErrorResponse::make(502, (string) $errors->first(), 'Download Error'));
        }

        DB::transaction(function () use ($gids, $series, $selectedEpisodes, $user): void {
            $gids->each(function (string $gid, int $index) use ($series, $selectedEpisodes, $user): void {
                MediaDownloadRef::fromSeriesAndEpisode($gid, $series, $selectedEpisodes[$index], $user)->saveOrFail();
            });
        }, attempts: self::SAVE_ATTEMPTS);

        return new ApiActionResource([
            'id' => "series-download:{$series->series_id}",
            'type' => 'download-requests',
            'attributes' => [
                'gids' => $gids->values()->all(),
                'count' => $gids->count(),
            ],
        ]);
    }
}
