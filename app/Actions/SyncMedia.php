<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\AsAction;
use App\Http\Integrations\LionzTv\Requests\GetSeriesCategoriesRequest;
use App\Http\Integrations\LionzTv\Requests\GetSeriesRequest;
use App\Http\Integrations\LionzTv\Requests\GetVodCategoriesRequest;
use App\Http\Integrations\LionzTv\Requests\GetVodStreamsRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\Category;
use App\Models\Series;
use App\Models\VodStream;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Telescope\Telescope;

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
        // Fetch categories
        Log::debug('Fetching movie categories from Xtream Codes API');
        /** @var Collection<array<string, mixed>> $movieCategories */
        $movieCategories = $this->connector->send(new GetVodCategoriesRequest)->dtoOrFail();

        Log::debug('Fetching series categories from Xtream Codes API');
        /** @var Collection<array<string, mixed>> $seriesCategories */
        $seriesCategories = $this->connector->send(new GetSeriesCategoriesRequest)->dtoOrFail();

        // Fetch series and VOD streams
        Log::debug('Fetching series from Xtream Codes API');
        /** @var Collection<array<string, mixed>> $series */
        $series = $this->connector->send(new GetSeriesRequest)->dtoOrFail();
        Log::debug('Fetching VOD streams from Xtream Codes API');
        /** @var Collection<array<string, mixed>> $vodStreams */
        $vodStreams = $this->connector->send(new GetVodStreamsRequest)->dtoOrFail();

        DB::transaction(function () use ($movieCategories, $seriesCategories, $series, $vodStreams): void {
            // Remove items from search index
            Log::debug('Removing items from search index');
            VodStream::removeAllFromSearch();
            Series::removeAllFromSearch();

            // Remove all existing categories, series and VOD streams
            Log::debug('Deleting all existing categories');
            Category::query()->truncate();
            Log::debug('Deleting all existing series');
            Series::query()->truncate();
            Log::debug('Deleting all existing VOD streams');
            VodStream::query()->truncate();

            Telescope::withoutRecording(function () use ($movieCategories, $seriesCategories, $series, $vodStreams): void {
                // Save categories
                $movieCategories->map(fn (array $category) => [
                    'category_id' => $category['category_id'],
                    'category_name' => $category['category_name'],
                    'parent_id' => $category['parent_id'],
                    'type' => 'movie',
                ])->chunk(1000)->each(function ($c): void {
                    Category::query()->upsert(
                        $c->toArray(),
                        ['category_id', 'type'],
                        ['category_name', 'parent_id']
                    );
                });

                $seriesCategories->map(fn (array $category) => [
                    'category_id' => $category['category_id'],
                    'category_name' => $category['category_name'],
                    'parent_id' => $category['parent_id'],
                    'type' => 'series',
                ])->chunk(1000)->each(function ($c): void {
                    Category::query()->upsert(
                        $c->toArray(),
                        ['category_id', 'type'],
                        ['category_name', 'parent_id']
                    );
                });
                Log::debug('Saved categories');

                $series->chunk(1000)->each(function ($c): void {
                    $saved = Series::query()->upsert(
                        $c->toArray(),
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
                });

                $vodStreams->chunk(1000)->each(function ($c): void {
                    $saved = VodStream::query()->upsert(
                        $c->toArray(),
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
                });
            });

            Log::debug('Marking series as searchable');
            Series::makeAllSearchable(3000);
            Log::debug('Marking VOD streams as searchable');
            VodStream::makeAllSearchable(3000);
            Log::info('All media contents have been refreshed');

        });
    }
}
