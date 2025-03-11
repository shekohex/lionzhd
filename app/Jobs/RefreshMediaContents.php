<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Client\XtreamCodesClient;
use App\Models\Series;
use App\Models\VodStream;
use Exception;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
    public function handle(XtreamCodesClient $client): void
    {
        try {
            $this->work($client);
        } catch (Exception $exception) {
            $this->fail($exception);
        } finally {
            $this->release();
        }
    }

    /**
     * Do the actual work of refreshing media contents.
     */
    public function work(XtreamCodesClient $client): void
    {
        // Check if the client is authenticated
        $client->authenticate();
        Log::debug('Authenticated with Xtream Codes API');
        // Fetch series and VOD streams

        Log::debug('Fetching series from Xtream Codes API');
        $series = $client->series();
        Log::debug('Fetching VOD streams from Xtream Codes API');
        $vodStreams = $client->vodStreams();

        DB::transaction(function () use ($series, $vodStreams): void {
            // Delete all existing series and VOD streams.
            Log::debug('Deleted all existing series');
            VodStream::query()->truncate();
            VodStream::removeAllFromSearch();
            Log::debug('Deleted all existing VOD streams');
            Series::query()->truncate();
            Series::removeAllFromSearch();

            $chunks = $series
                ->map(function (array $it) {
                    $it['backdrop_path'] = json_encode($it['backdrop_path']);

                    return $it;
                })
                ->chunk(1000);
            foreach ($chunks as $c) {
                $saved = Series::query()->insert($c->all());
                if ($saved) {
                    Log::debug('Saved series chunk');
                } else {
                    Log::warning('Failed to save series chunk');
                }
            }

            $chunks = $vodStreams->chunk(1000);
            foreach ($chunks as $c) {
                $saved = VodStream::query()->insert($c->all());
                if ($saved) {
                    Log::debug('Saved VOD stream chunk');
                } else {
                    Log::warning('Failed to save VOD stream chunk');
                }
            }

            Log::debug('Marking series as searchable');
            Series::makeAllSearchable(3000);
            Log::debug('Marking VOD streams as searchable');
            VodStream::makeAllSearchable(3000);
            Log::info('All media contents have been refreshed');
        });
    }
}
