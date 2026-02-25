<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\SyncCategories as SyncCategoriesAction;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

final class SyncCategories implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const string LOCK_KEY = 'sync:categories';

    private const int LOCK_TTL_SECONDS = 600;

    private const int REQUEUE_DELAY_SECONDS = 15;

    /**
     * @var int
     */
    public $tries = 30;

    public function __construct(
        public readonly bool $forceEmptyVod = false,
        public readonly bool $forceEmptySeries = false,
        public readonly ?int $requestedByUserId = null,
    ) {}

    public function handle(): void
    {
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_TTL_SECONDS);

        if (! $lock->get()) {
            $this->release(self::REQUEUE_DELAY_SECONDS);

            return;
        }

        try {
            SyncCategoriesAction::run(
                forceEmptyVod: $this->forceEmptyVod,
                forceEmptySeries: $this->forceEmptySeries,
                requestedByUserId: $this->requestedByUserId,
            );
        } finally {
            $this->releaseLock($lock);
        }
    }

    private function releaseLock(Lock $lock): void
    {
        if ($lock->owner() === null) {
            return;
        }

        $lock->release();
    }
}
