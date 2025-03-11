<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Client\XtreamCodesClient;
use App\Jobs\RefreshMediaContents;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class SyncMedia extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lionz:sync-media';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync media content from Xtream Codes API';

    /**
     * Execute the console command.
     */
    public function handle(RefreshMediaContents $job, XtreamCodesClient $client): int
    {
        $this->info('Starting media synchronization...');

        try {
            // Dispatch the job and wait for it to finish
            $this->info('Running RefreshMediaContents job...');
            $job->work($client);
            $this->info('Media synchronization completed successfully!');

            return Command::SUCCESS;
        } catch (Exception $exception) {
            $this->error('Media synchronization failed: '.$exception->getMessage());
            Log::error($exception->getMessage(), ['exception' => $exception]);

            return Command::FAILURE;
        }
    }
}
