<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\AsAction;
use App\Http\Integrations\LionzTv\Requests\GetSeriesRequest;
use App\Http\Integrations\LionzTv\Requests\GetVodStreamsRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\Series;
use App\Models\VodStream;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;

/**
 * Sync media content from Xtream Codes API
 */
final readonly class SyncMedia
{
    use AsAction;

    public function __construct(private XtreamCodesConnector $connector) {}

    /**
     * Execute the action.
     */
    public function __invoke(): void
    {

        // Fetch series and VOD streams
        Log::debug('Fetching series from Xtream Codes API');
        /** @var LazyCollection<array<string, mixed>> $series */
        $series = $this->connector->send(new GetSeriesRequest)->dtoOrFail();
        Log::debug('Fetching VOD streams from Xtream Codes API');
        /** @var LazyCollection<array<string, mixed>> $vodStreams */
        $vodStreams = $this->connector->send(new GetVodStreamsRequest)->dtoOrFail();

        DB::transaction(function () use ($series, $vodStreams): void {
            // Delete all existing series and VOD streams.
            Log::debug('Deleted all existing series');
            VodStream::query()->truncate();
            VodStream::removeAllFromSearch();
            Log::debug('Deleted all existing VOD streams');
            Series::query()->truncate();
            Series::removeAllFromSearch();

            $chunks = $series->chunk(1000);
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
