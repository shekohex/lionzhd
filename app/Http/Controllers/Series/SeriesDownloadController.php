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
use App\Http\Controllers\Controller;
use App\Http\Integrations\LionzTv\Requests\GetSeriesInfoRequest;
use App\Http\Integrations\LionzTv\Responses\Episode;
use App\Http\Integrations\LionzTv\Responses\SeriesInformation;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\MediaDownloadRef;
use App\Models\Series;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class SeriesDownloadController extends Controller
{
    private const int DOWNLOAD_LOCK_TTL_SECONDS = 15;

    private const int DOWNLOAD_LOCK_WAIT_SECONDS = 3;

    private const int SAVE_ATTEMPTS = 5;

    /**
     * Trigger a download of the series.
     */
    public function create(#[CurrentUser] User $user, Request $request, XtreamCodesConnector $client, Series $model, int $season, int $episode): RedirectResponse
    {

        $series = $client->send(new GetSeriesInfoRequest($model->series_id));
        $dto = $series->dtoOrFail();
        /** @var ?Episode */
        $selectedEpisode = $dto->seasonsWithEpisodes[$season][$episode];
        if ($selectedEpisode === null) {
            return back()->withErrors('Episode not found.');
        }

        try {
            $queuedDownload = Cache::lock(
                MediaDownloadRef::lockKeyForSeriesEpisode($user, $model, $selectedEpisode),
                self::DOWNLOAD_LOCK_TTL_SECONDS,
            )->block(self::DOWNLOAD_LOCK_WAIT_SECONDS, fn (): array => $this->queueEpisodeDownload($user, $model, $dto, $selectedEpisode));
        } catch (LockTimeoutException) {
            $activeDownload = GetActiveDownloads::run($model, $selectedEpisode, $user);

            if ($activeDownload !== null) {
                return $this->downloadsRedirect($request, [
                    'episode' => $selectedEpisode->episodeNum,
                    'downloadable_id' => $selectedEpisode->id,
                    'series_id' => $model->series_id,
                    'gid' => $activeDownload->gid,
                ])->with('success', 'Download already in progress.');
            }

            return back()->withErrors([
                'download' => 'Download is already being prepared. Please try again.',
            ]);
        }

        return $this->downloadsRedirect($request, [
            'episode' => $selectedEpisode->episodeNum,
            'downloadable_id' => $selectedEpisode->id,
            'series_id' => $model->series_id,
            'gid' => $queuedDownload['gid'],
        ])->with('success', $queuedDownload['existing'] ? 'Download already in progress.' : 'Download started.');
    }

    public function store(#[CurrentUser] User $user, XtreamCodesConnector $client, Series $model, BatchDownloadEpisodesData $requestData, Request $request): RedirectResponse
    {

        $series = $client->send(new GetSeriesInfoRequest($model->series_id));
        $dto = $series->dtoOrFail();
        /** @var Episode[] */
        $selectedEpisodes = [];
        /** @var string[] */
        $errors = [];
        foreach ($requestData->selectedEpisodes as $episode) {
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

        $saved = DB::transaction(function () use ($gids, $model, $selectedEpisodes, $user): bool {
            $gids->each(function (string $gid, int $index) use ($model, $selectedEpisodes, $user): void {
                $selectedEpisode = $selectedEpisodes[$index];
                MediaDownloadRef::fromSeriesAndEpisode($gid, $model, $selectedEpisode, $user)->saveOrFail();
            });

            return true;
        }, attempts: self::SAVE_ATTEMPTS);

        if (! $saved) {
            return back()->withErrors('Failed to save download references.');
        }

        return $this->downloadsRedirect($request)->with('success', 'Downloads started for selected episodes.');
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

        return new SymfonyResponse(view('direct-download.start', [
            'signedUrl' => $signedUrl,
        ])->render(), 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * Create a batch of direct download links for selected episodes as a text file.
     */
    public function batchDirectTxt(XtreamCodesConnector $client, Series $model, BatchDownloadEpisodesData $request): SymfonyResponse
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

        $selectedEpisodesData = $request->selectedEpisodes;

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

        $result = $signedUrls->prepend('# Direct Download Links for Series, copy one by one or use a download manager');
        $content = $result->implode(PHP_EOL);

        return new Response($content, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="direct-links.txt"',
        ]);
    }

    private function downloadsRedirect(Request $request, array $parameters = []): RedirectResponse
    {
        $returnTo = $request->query('return_to');

        if (is_string($returnTo) && preg_match('#^/downloads(?:[/?]|$)#', $returnTo) === 1) {
            return redirect()->to($returnTo);
        }

        return redirect()->route('downloads', $parameters);
    }

    private function queueEpisodeDownload(User $user, Series $model, SeriesInformation $dto, Episode $selectedEpisode): array
    {
        $activeDownload = GetActiveDownloads::run($model, $selectedEpisode, $user);

        if ($activeDownload !== null) {
            return [
                'gid' => $activeDownload->gid,
                'existing' => true,
            ];
        }

        $url = CreateXtreamcodesDownloadUrl::run($selectedEpisode);
        $gid = DownloadMedia::run($url, ['out' => CreateDownloadOut::run($dto, $selectedEpisode)]);

        $this->persistDownloadRef(MediaDownloadRef::fromSeriesAndEpisode($gid, $model, $selectedEpisode, $user));

        return [
            'gid' => $gid,
            'existing' => false,
        ];
    }

    private function persistDownloadRef(MediaDownloadRef $downloadRef): void
    {
        $saved = DB::transaction(static fn (): bool => $downloadRef->save(), attempts: self::SAVE_ATTEMPTS);

        if (! $saved) {
            throw new RuntimeException('Failed to save download reference.');
        }
    }
}
