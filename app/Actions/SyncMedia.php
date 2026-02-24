<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\AsAction;
use App\Http\Integrations\LionzTv\Requests\GetSeriesRequest;
use App\Http\Integrations\LionzTv\Requests\GetVodStreamsRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\Series;
use App\Models\VodStream;
use Illuminate\Support\Facades\Log;
use Laravel\Telescope\Telescope;
use Throwable;

/**
 * Sync media content from Xtream Codes API
 *
 * @method static void run()
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
        $this->removeAllFromSearchSafely();

        Log::debug('Deleting all existing series');
        Series::query()->delete();
        Log::debug('Deleting all existing VOD streams');
        VodStream::query()->delete();

        Telescope::withoutRecording(function (): void {
            Log::debug('Fetching series from Xtream Codes API');
            /** @var array<int, array<string, mixed>> $series */
            $series = $this->connector->send(new GetSeriesRequest)->dtoOrFail();

            foreach (array_chunk($series, 1000) as $chunk) {
                $saved = Series::query()->upsert(
                    $chunk,
                    ['series_id'],
                    [
                        'num', 'name', 'cover', 'plot', 'cast',
                        'director', 'genre', 'releaseDate', 'last_modified',
                        'rating', 'rating_5based', 'backdrop_path',
                        'youtube_trailer', 'episode_run_time', 'category_id',
                    ]
                );

                if ($saved) {
                    Log::debug('Saved series chunk');
                } else {
                    Log::warning('Failed to save series chunk');
                }
            }

            unset($series);
            gc_collect_cycles();

            Log::debug('Fetching VOD streams from Xtream Codes API');
            /** @var array<int, array<string, mixed>> $vodStreams */
            $vodStreams = $this->connector->send(new GetVodStreamsRequest)->dtoOrFail();

            foreach (array_chunk($vodStreams, 1000) as $chunk) {
                $saved = VodStream::query()->upsert(
                    $chunk,
                    ['stream_id'],
                    [
                        'num',
                        'name',
                        'stream_type',
                        'stream_icon',
                        'rating',
                        'rating_5based',
                        'added',
                        'is_adult',
                        'category_id',
                        'container_extension',
                        'custom_sid',
                        'direct_source',
                    ]
                );

                if ($saved) {
                    Log::debug('Saved VOD stream chunk');
                } else {
                    Log::warning('Failed to save VOD stream chunk');
                }
            }
        });

        $this->makeAllSearchableSafely();
        Log::info('All media contents have been refreshed');
    }

    private function removeAllFromSearchSafely(): void
    {
        try {
            Log::debug('Removing items from search index');
            VodStream::removeAllFromSearch();
            Series::removeAllFromSearch();
        } catch (Throwable $exception) {
            Log::warning('Skipping search index cleanup', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function makeAllSearchableSafely(): void
    {
        try {
            Log::debug('Marking series as searchable');
            Series::makeAllSearchable(3000);
            Log::debug('Marking VOD streams as searchable');
            VodStream::makeAllSearchable(3000);
        } catch (Throwable $exception) {
            Log::warning('Skipping search indexing', [
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
