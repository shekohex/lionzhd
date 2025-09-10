<?php

declare(strict_types=1);

namespace App\Http\Controllers\Series;

use App\Actions\BatchCreateSignedDirectLinks;
use App\Actions\BatchDownloadMedia;
use App\Actions\CreateDownloadOut;
use App\Actions\CreateSignedDirectLink;
use App\Actions\CreateXtreamcodesDownloadUrl;
use App\Actions\DownloadMedia;
use App\Actions\GetActiveDownloads;
use App\Data\BatchDownloadEpisodesData;
use App\Data\SelectedEpisodeData;
use App\Http\Controllers\Controller;
use App\Http\Integrations\LionzTv\Requests\GetSeriesInfoRequest;
use App\Http\Integrations\LionzTv\Responses\Episode;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\MediaDownloadRef;
use App\Models\Series;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class SeriesDownloadController extends Controller
{
    /**
     * Trigger a download of the series.
     */
    public function create(XtreamCodesConnector $client, Series $model, int $season, int $episode): RedirectResponse
    {

        $series = $client->send(new GetSeriesInfoRequest($model->series_id));
        $dto = $series->dtoOrFail();
        /** @var ?Episode */
        $selectedEpisode = $dto->seasonsWithEpisodes[$season][$episode];
        if ($selectedEpisode === null) {
            return back()->withErrors('Episode not found.');
        }

        $activeDownload = GetActiveDownloads::run($model, $selectedEpisode);

        if ($activeDownload) {
            return redirect()->route('downloads', [
                'episode' => $selectedEpisode->episodeNum,
                'downloadable_id' => $selectedEpisode->id,
                'series_id' => $model->series_id,
                'gid' => $activeDownload->gid,
            ])->with('success', 'Download already in progress.');
        }

        $url = CreateXtreamcodesDownloadUrl::run($selectedEpisode);
        $gid = DownloadMedia::run($url, ['out' => CreateDownloadOut::run($dto, $selectedEpisode)]);

        MediaDownloadRef::fromSeriesAndEpisode($gid, $model, $selectedEpisode)->saveOrFail();

        return redirect()->route('downloads', [
            'episode' => $selectedEpisode->episodeNum,
            'downloadable_id' => $selectedEpisode->id,
            'series_id' => $model->series_id,
            'gid' => $gid,
        ])->with('success', 'Download started.');
    }

    public function store(XtreamCodesConnector $client, Series $model, BatchDownloadEpisodesData $request): RedirectResponse
    {

        $series = $client->send(new GetSeriesInfoRequest($model->series_id));
        $dto = $series->dtoOrFail();
        /** @var Episode[] */
        $selectedEpisodes = [];
        /** @var string[] */
        $errors = [];
        foreach ($request->selectedEpisodes as $episode) {
            $selectedEpisode = $dto->seasonsWithEpisodes[$episode->season][$episode->episodeNum];
            if ($selectedEpisode === null) {
                $errors[] = "S{$episode->season}E{$episode->episodeNum} not found.";

                continue;
            }

            $selectedEpisodes[] = $selectedEpisode;
        }

        if (! empty($errors)) {
            return back()->withErrors($errors);
        }

        $urls = collect($selectedEpisodes)->map(fn (Episode $selectedEpisode) => CreateXtreamcodesDownloadUrl::run($selectedEpisode));

        $gids = BatchDownloadMedia::run($urls->toArray(), fn (int $index) => [
            'out' => CreateDownloadOut::run($dto, $selectedEpisodes[$index]),
        ]);

        $errors = $gids->filter(fn (mixed $response) => is_array($response) && isset($response['error']))->map(fn (array $response) => $response['error']);

        if ($errors->isNotEmpty()) {
            return back()->withErrors($errors->toArray());
        }

        $saved = DB::transaction(function () use ($gids, $model, $selectedEpisodes): bool {
            $gids->each(function (string $gid, int $index) use ($model, $selectedEpisodes): void {
                $selectedEpisode = $selectedEpisodes[$index];
                MediaDownloadRef::fromSeriesAndEpisode($gid, $model, $selectedEpisode)->saveOrFail();
            });

            return true;
        });

        if (! $saved) {
            return back()->withErrors('Failed to save download references.');
        }

        return redirect()->route('downloads')->with('success', 'Downloads started for selected episodes.');
    }

    /**
     * Create a direct download link for a single episode.
     */
    public function direct(XtreamCodesConnector $client, Series $model, int $season, int $episode, Request $request): RedirectResponse|SymfonyResponse
    {
        if (! config('features.direct_download_links', false)) {
            abort(404);
        }

        $series = $client->send(new GetSeriesInfoRequest($model->series_id));
        $dto = $series->dtoOrFail();

        /** @var ?Episode */
        $selectedEpisode = $dto->seasonsWithEpisodes[$season][$episode];
        if ($selectedEpisode === null) {
            return back()->withErrors('Episode not found.');
        }

        $signedUrl = CreateSignedDirectLink::run($selectedEpisode);

        return redirect()->to($signedUrl);
    }

    /**
     * Create a batch of direct download links for selected episodes as a text file.
     */
    public function batchDirectTxt(XtreamCodesConnector $client, Series $model, Request $request): SymfonyResponse
    {
        if (! config('features.direct_download_links', false)) {
            abort(404);
        }

        $series = $client->send(new GetSeriesInfoRequest($model->series_id));
        $dto = $series->dtoOrFail();

        /** @var Episode[] */
        $selectedEpisodes = [];
        /** @var string[] */
        $errors = [];

        // Use the SelectedEpisodeData DTO for validation
        $selectedEpisodesData = SelectedEpisodeData::collect($request->input('selectedEpisodes', []));

        foreach ($selectedEpisodesData as $episodeData) {
            $seasonIndex = $episodeData->season;
            $episodeIndex = $episodeData->episodeNum;

            if (! isset($dto->seasonsWithEpisodes[$seasonIndex][$episodeIndex])) {
                $errors[] = "S{$seasonIndex}E{$episodeIndex} not found.";

                continue;
            }

            $selectedEpisodes[] = $dto->seasonsWithEpisodes[$seasonIndex][$episodeIndex];
        }

        if (! empty($errors)) {
            return back()->withErrors($errors);
        }

        if (empty($selectedEpisodes)) {
            return back()->withErrors('No episodes selected.');
        }

        $signedUrls = BatchCreateSignedDirectLinks::run($selectedEpisodes);

        $content = $signedUrls->implode(PHP_EOL);

        return new Response($content, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="direct-links.txt"',
        ]);
    }
}
