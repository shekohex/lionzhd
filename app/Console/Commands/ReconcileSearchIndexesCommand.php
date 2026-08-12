<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ReconcileSearchIndexes;
use Illuminate\Console\Command;

final class ReconcileSearchIndexesCommand extends Command
{
    protected $signature = 'lionz:reconcile-search {--force : Rebuild indexes even when health checks pass}';

    protected $description = 'Safely reconcile search indexes with database records';

    public function handle(ReconcileSearchIndexes $reconcileSearchIndexes): int
    {
        $reconcileSearchIndexes((bool) $this->option('force'));
        $this->info('Search index reconciliation completed.');

        return self::SUCCESS;
    }
}
