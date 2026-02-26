<?php

declare(strict_types=1);

namespace App\Actions\Downloads;

use App\Actions\CreateDownloadOut;
use App\Actions\CreateXtreamcodesDownloadUrl;
use App\Actions\DownloadMedia;
use App\Concerns\AsAction;
use App\Http\Integrations\Aria2\JsonRpcConnector;
use App\Http\Integrations\Aria2\JsonRpcException;
use App\Http\Integrations\Aria2\Requests\RemoveDownloadResultRequest;
use App\Http\Integrations\Aria2\Requests\TellStatusRequest;
use App\Http\Integrations\Aria2\Responses\TellStatusResponse;
use App\Http\Integrations\LionzTv\Requests\GetSeriesInfoRequest;
use App\Http\Integrations\LionzTv\Requests\GetVodInfoRequest;
use App\Http\Integrations\LionzTv\Responses\Episode;
use App\Http\Integrations\LionzTv\Responses\SeriesInformation;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\MediaDownloadRef;
use Throwable;

/**
 * @method static null|string run(MediaDownloadRef $download, bool $restartFromZero = false, bool $resetRetryAttempt = false)
 */
final readonly class RetryDownload
{
    use AsAction;

    public function __construct(
        private JsonRpcConnector $aria2Connector,
        private XtreamCodesConnector $xtreamCodesConnector,
        private DeleteDownloadFiles $deleteDownloadFiles,
    ) {}

    public function __invoke(MediaDownloadRef $download, bool $restartFromZero = false, bool $resetRetryAttempt = false): ?string
    {
        if ($download->canceled_at !== null) {
            return 'This download is already canceled and cannot be retried.';
        }

        if (! $download->isVodStream() && ! $download->isSeriesWithEpisode()) {
            return 'This download cannot be retried because media metadata is incomplete.';
        }

        if ($restartFromZero) {
            $restartError = $this->restartFromZero($download);

            if ($restartError !== null) {
                return $restartError;
            }
        }

        $this->removeDownloadResultBestEffort($download->gid);

        try {
            [$url, $out] = $this->buildRetryTarget($download);
            $newGid = DownloadMedia::run($url, ['out' => $out]);
        } catch (Throwable $exception) {
            return $exception->getMessage();
        }

        $updates = [
            'gid' => $newGid,
            'retry_next_at' => null,
            'last_error_code' => null,
            'last_error_message' => null,
        ];

        if ($resetRetryAttempt) {
            $updates['retry_attempt'] = 0;
        }

        $download->forceFill($updates)->save();

        return null;
    }

    private function restartFromZero(MediaDownloadRef $download): ?string
    {
        $downloadDir = null;
        $downloadFiles = $this->normalizeDownloadFiles($download->download_files);

        if ($downloadFiles === []) {
            [$downloadDir, $downloadFiles] = $this->fetchDownloadFilesSnapshot($download);

            if ($downloadFiles !== []) {
                $download->forceFill(['download_files' => $downloadFiles])->save();
            }
        }

        return $this->deleteDownloadFiles->handle($downloadFiles, $downloadDir);
    }

    /**
     * @return array{0: ?string, 1: list<string>}
     */
    private function fetchDownloadFilesSnapshot(MediaDownloadRef $download): array
    {
        try {
            /** @var TellStatusResponse $statusResponse */
            $statusResponse = $this->aria2Connector->send(
                new TellStatusRequest($download->gid, ['gid', 'dir', 'files'])
            )->dtoOrFail();

            if ($statusResponse->hasError()) {
                return [null, []];
            }

            $status = $statusResponse->getStatus();
            $downloadDir = is_string($status['dir'] ?? null) ? $status['dir'] : null;
            $downloadFiles = collect($status['files'] ?? [])
                ->map(static fn (mixed $file): ?string => is_array($file) && is_string($file['path'] ?? null) ? $file['path'] : null)
                ->filter(static fn (?string $path): bool => $path !== null && $path !== '')
                ->values()
                ->all();

            return [$downloadDir, $downloadFiles];
        } catch (JsonRpcException) {
            return [null, []];
        }
    }

    private function removeDownloadResultBestEffort(string $gid): void
    {
        try {
            $this->aria2Connector->send(new RemoveDownloadResultRequest($gid))->dtoOrFail();
        } catch (Throwable) {
        }
    }

    /**
     * @return array{0: \League\Uri\Uri, 1: string}
     */
    private function buildRetryTarget(MediaDownloadRef $download): array
    {
        if ($download->isVodStream()) {
            $vodInfo = $this->xtreamCodesConnector->send(
                new GetVodInfoRequest($download->downloadable_id)
            )->dtoOrFail();

            return [
                CreateXtreamcodesDownloadUrl::run($vodInfo),
                CreateDownloadOut::run($vodInfo),
            ];
        }

        $seriesInfo = $this->xtreamCodesConnector->send(
            new GetSeriesInfoRequest($download->media_id)
        )->dtoOrFail();

        $episode = $this->resolveEpisode($download, $seriesInfo);

        if (! $episode instanceof Episode) {
            throw new \RuntimeException('Unable to locate the selected episode for retry.');
        }

        return [
            CreateXtreamcodesDownloadUrl::run($episode),
            CreateDownloadOut::run($seriesInfo, $episode),
        ];
    }

    private function resolveEpisode(MediaDownloadRef $download, SeriesInformation $seriesInfo): ?Episode
    {
        if ($download->season !== null && $download->episode !== null) {
            $episode = $seriesInfo->seasonsWithEpisodes[$download->season][$download->episode] ?? null;

            if ($episode instanceof Episode) {
                return $episode;
            }
        }

        foreach ($seriesInfo->seasonsWithEpisodes as $episodes) {
            foreach ($episodes as $episode) {
                if ((int) $episode->id === $download->downloadable_id) {
                    return $episode;
                }
            }
        }

        return null;
    }

    /**
     * @param  mixed  $rawDownloadFiles
     * @return list<string>
     */
    private function normalizeDownloadFiles(mixed $rawDownloadFiles): array
    {
        if (! is_array($rawDownloadFiles)) {
            return [];
        }

        return collect($rawDownloadFiles)
            ->filter(static fn (mixed $path): bool => is_string($path) && $path !== '')
            ->values()
            ->all();
    }
}
