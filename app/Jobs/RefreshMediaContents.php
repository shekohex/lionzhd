<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\SyncMedia;
use Exception;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class RefreshMediaContents implements ShouldQueue, ShouldBeUnique
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of seconds after which the job's unique lock will be released.
     *
     * @var int
     */
    public $uniqueFor = 600;

    /**
     * The maximum number of attempts for this job.
     *
     * @var int
     */
    public $tries = 10;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        // Exponential backoff: 1min, 2min, 4min, etc.
        return [60, 120, 240, 480, 960, 1920, 3840, 7680, 15360, 30720];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            SyncMedia::run();
        } catch (Exception $exception) {
            $this->fail($exception);
        } finally {
            $this->release();
        }
    }
}
