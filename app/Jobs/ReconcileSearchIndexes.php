<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\ReconcileSearchIndexes as ReconcileSearchIndexesAction;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ReconcileSearchIndexes implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function handle(ReconcileSearchIndexesAction $reconcileSearchIndexes): void
    {
        $reconcileSearchIndexes();
    }
}
