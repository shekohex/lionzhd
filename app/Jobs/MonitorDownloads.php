<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Downloads\ClassifyDownloadFailure;
use App\Actions\Downloads\ComputeRetryBackoff;
use App\Actions\GetDownloadStatus;
use App\Http\Integrations\Aria2\JsonRpcConnector;
use App\Http\Integrations\Aria2\JsonRpcException;
use App\Http\Integrations\Aria2\Requests\PauseRequest;
use App\Models\MediaDownloadRef;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class MonitorDownloads implements ShouldQueue, ShouldBeUnique
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const int MAX_AUTO_RETRY_ATTEMPTS = 5;

    public int $uniqueFor = 50;

    public int $tries = 1;

    public function __construct(private readonly JsonRpcConnector $connector) {}

    public function handle(): void
    {
        $downloads = MediaDownloadRef::query()
            ->whereNull('canceled_at')
            ->get();

        if ($downloads->isEmpty()) {
            return;
        }

        $statuses = GetDownloadStatus::run(
            $downloads->pluck('gid')->all(),
            ['gid', 'status', 'errorCode', 'errorMessage', 'dir', 'files', 'totalLength', 'completedLength', 'downloadSpeed'],
        )->keyBy('gid');

        foreach ($downloads as $download) {
            $status = $statuses->get($download->gid);

            if (! is_array($status) || isset($status['error'])) {
                continue;
            }

            $this->persistDownloadFilesSnapshot($download, $status);
            $this->enforceStickyPause($download, $status);
            $this->processErrorStatus($download, $status);
        }
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function persistDownloadFilesSnapshot(MediaDownloadRef $download, array $status): void
    {
        if ($download->download_files !== null) {
            return;
        }

        $downloadFiles = collect($status['files'] ?? [])
            ->map(static fn (mixed $file): ?string => is_array($file) && is_string($file['path'] ?? null) ? $file['path'] : null)
            ->filter(static fn (?string $path): bool => $path !== null && $path !== '')
            ->values()
            ->all();

        if ($downloadFiles === []) {
            return;
        }

        $download->forceFill([
            'download_files' => $downloadFiles,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function enforceStickyPause(MediaDownloadRef $download, array $status): void
    {
        if (! $download->desired_paused) {
            return;
        }

        $aria2Status = (string) ($status['status'] ?? '');

        if (! in_array($aria2Status, ['active', 'waiting'], true)) {
            return;
        }

        try {
            $this->connector->send(new PauseRequest($download->gid))->dtoOrFail();
        } catch (JsonRpcException) {
        }
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function processErrorStatus(MediaDownloadRef $download, array $status): void
    {
        if (($status['status'] ?? null) !== 'error') {
            return;
        }

        $errorCode = (int) ($status['errorCode'] ?? 0);
        $errorMessage = is_string($status['errorMessage'] ?? null) ? $status['errorMessage'] : null;

        $updates = [
            'last_error_code' => $errorCode,
            'last_error_message' => $errorMessage,
        ];

        if ($download->retry_next_at !== null && now()->lt($download->retry_next_at)) {
            $download->forceFill($updates)->save();

            return;
        }

        $classification = ClassifyDownloadFailure::run($errorCode, $errorMessage);

        if (! $classification['isTransient'] || $download->retry_attempt >= self::MAX_AUTO_RETRY_ATTEMPTS) {
            $download->forceFill($updates)->save();

            return;
        }

        $attempt = $download->retry_attempt + 1;
        $retryNextAt = now()->addSeconds(ComputeRetryBackoff::run($attempt));

        $download->forceFill([
            ...$updates,
            'retry_attempt' => $attempt,
            'retry_next_at' => $retryNextAt,
        ])->save();

        RetryDownload::dispatch($download->id)->delay($retryNextAt);
    }
}
