<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Downloads\RetryDownload as RetryDownloadAction;
use App\Models\MediaDownloadRef;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class RetryDownload implements ShouldQueue, ShouldBeUnique
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $downloadRefId) {}

    public function uniqueId(): string
    {
        return (string) $this->downloadRefId;
    }

    public function handle(RetryDownloadAction $retryDownload): void
    {
        $download = MediaDownloadRef::query()->find($this->downloadRefId);

        if (! $download instanceof MediaDownloadRef) {
            return;
        }

        if ($download->canceled_at !== null || $download->desired_paused) {
            return;
        }

        if ($download->retry_next_at === null || now()->lt($download->retry_next_at)) {
            return;
        }

        $retryDownload->handle($download);
    }
}
