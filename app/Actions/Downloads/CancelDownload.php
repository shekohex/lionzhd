<?php

declare(strict_types=1);

namespace App\Actions\Downloads;

use App\Concerns\AsAction;
use App\Http\Integrations\Aria2\JsonRpcConnector;
use App\Http\Integrations\Aria2\JsonRpcException;
use App\Http\Integrations\Aria2\Requests\ForceRemoveRequest;
use App\Http\Integrations\Aria2\Requests\TellStatusRequest;
use App\Http\Integrations\Aria2\Responses\RemoveResponse;
use App\Http\Integrations\Aria2\Responses\TellStatusResponse;
use App\Models\MediaDownloadRef;

/**
 * @method static null|string run(MediaDownloadRef $download, bool $deletePartial = false)
 */
final readonly class CancelDownload
{
    use AsAction;

    public function __construct(
        private JsonRpcConnector $connector,
        private DeleteDownloadFiles $deleteDownloadFiles,
    ) {}

    public function __invoke(MediaDownloadRef $download, bool $deletePartial = false): ?string
    {
        if ($download->canceled_at !== null) {
            return null;
        }

        $downloadDir = null;
        $downloadFiles = [];

        try {
            /** @var TellStatusResponse $statusResponse */
            $statusResponse = $this->connector->send(
                new TellStatusRequest($download->gid, ['gid', 'dir', 'files', 'errorCode', 'errorMessage'])
            )->dtoOrFail();

            if (! $statusResponse->hasError()) {
                $status = $statusResponse->getStatus();

                $downloadDir = is_string($status['dir'] ?? null) ? $status['dir'] : null;
                $downloadFiles = collect($status['files'] ?? [])
                    ->map(static fn (mixed $file): ?string => is_array($file) && is_string($file['path'] ?? null) ? $file['path'] : null)
                    ->filter(static fn (?string $path): bool => $path !== null && $path !== '')
                    ->values()
                    ->all();
            }
        } catch (JsonRpcException) {
        }

        $download->forceFill([
            'download_files' => $downloadFiles,
            'cancel_delete_partial' => $deletePartial,
        ])->save();

        try {
            /** @var RemoveResponse $removeResponse */
            $removeResponse = $this->connector->send(new ForceRemoveRequest($download->gid))->dtoOrFail();

            if ($removeResponse->hasError()) {
                return $removeResponse->errorMessage();
            }
        } catch (JsonRpcException $exception) {
            return $exception->getMessage();
        }

        $download->forceFill([
            'canceled_at' => now(),
        ])->save();

        if ($deletePartial) {
            return $this->deleteDownloadFiles->handle($downloadFiles, $downloadDir);
        }

        return null;
    }
}
