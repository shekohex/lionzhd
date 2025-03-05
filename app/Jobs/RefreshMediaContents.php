<?php

namespace App\Jobs;

use App\Models\Series;
use App\Models\VodStream;
use App\Services\XtreamCodesClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshMediaContents implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds after which the job's unique lock will be released.
     *
     * @var int
     */
    public $uniqueFor = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly XtreamCodesClient $client,
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Check if the client is authenticated
        $this->client->authenticate();
        // Fetch series and VOD streams
        $series = $this->client->series();
        $vodStreams = $this->client->vodStreams();
        // Save or update series and VOD streams in the database
        Series::query()->upsert($series, ['num']);
        VodStream::query()->upsert($vodStreams, ['num']);
    }
}
