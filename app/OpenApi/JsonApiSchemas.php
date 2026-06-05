<?php

declare(strict_types=1);

namespace App\OpenApi;

use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;

final class JsonApiSchemas
{
    public static function apply(OpenApi $openApi): void
    {
        $movie = self::resource('movies', self::movieAttributes());
        $series = self::resource('series', self::seriesAttributes());
        $download = self::resource('downloads', self::downloadAttributes());
        $seriesMonitor = self::resource('series-monitors', self::seriesMonitorAttributes());

        $openApi->components->addSchema('App\\Http\\Resources\\Api\\MovieResource', Schema::fromType($movie));
        $openApi->components->addSchema('App\\Http\\Resources\\Api\\SeriesResource', Schema::fromType($series));
        $openApi->components->addSchema('App\\Http\\Resources\\Api\\MediaDownloadResource', Schema::fromType($download));
        $openApi->components->addSchema('App\\Http\\Resources\\Api\\SeriesMonitorResource', Schema::fromType($seriesMonitor));
        $openApi->components->addSchema('App\\Http\\Resources\\Api\\DiscoverResource', Schema::fromType(self::groupedResource('discover', $movie, $series)));
        $openApi->components->addSchema('App\\Http\\Resources\\Api\\SearchResultResource', Schema::fromType(self::groupedResource('search-results', $movie, $series, paginated: true)));
        $openApi->components->addSchema('App\\Http\\Resources\\Api\\ApiActionResource', Schema::fromType(self::apiActionResource()));
        $openApi->components->addSchema('MovieResource', Schema::fromType($movie));
        $openApi->components->addSchema('SeriesResource', Schema::fromType($series));
        $openApi->components->addSchema('MediaDownloadResource', Schema::fromType($download));
        $openApi->components->addSchema('SeriesMonitorResource', Schema::fromType($seriesMonitor));
        $openApi->components->addSchema('DiscoverResource', Schema::fromType(self::groupedResource('discover', $movie, $series)));
        $openApi->components->addSchema('SearchResultResource', Schema::fromType(self::groupedResource('search-results', $movie, $series, paginated: true)));
        $openApi->components->addSchema('ApiActionResource', Schema::fromType(self::apiActionResource()));
        $openApi->components->addSchema('JsonApiErrorDocument', Schema::fromType(self::errorDocument()));

        self::forceJsonApiErrorContent($openApi);
    }

    /**
     * @param  array<string, Type>  $attributes
     */
    private static function resource(string $type, array $attributes): ObjectType
    {
        return (new ObjectType)
            ->addProperty('id', new StringType)
            ->addProperty('type', (new StringType)->const($type))
            ->addProperty('attributes', self::attributes($attributes))
            ->setRequired(['id', 'type', 'attributes']);
    }

    private static function groupedResource(string $type, ObjectType $movie, ObjectType $series, bool $paginated = false): ObjectType
    {
        return self::resource($type, [
            'movies' => self::resourceDocument($movie, $paginated),
            'series' => self::resourceDocument($series, $paginated),
        ]);
    }

    private static function apiActionResource(): ObjectType
    {
        return self::resource('download-requests', [
            'gid' => (new StringType)->nullable(true),
            'existing' => new BooleanType,
            'url' => (new StringType)->nullable(true),
            'expires_in_seconds' => (new IntegerType)->nullable(true),
            'status' => (new StringType)->nullable(true),
            'gids' => (new ArrayType)->setItems(new StringType),
            'count' => (new IntegerType)->nullable(true),
            'series_id' => (new IntegerType)->nullable(true),
            'season' => (new IntegerType)->nullable(true),
            'episode' => (new IntegerType)->nullable(true),
            'episode_id' => (new StringType)->nullable(true),
        ])->addProperty('type', new StringType);
    }

    private static function errorDocument(): ObjectType
    {
        return (new ObjectType)
            ->addProperty('errors', (new ArrayType)->setItems(
                (new ObjectType)
                    ->addProperty('status', new StringType)
                    ->addProperty('title', new StringType)
                    ->addProperty('detail', new StringType)
                    ->addProperty('source', (new ObjectType)
                        ->addProperty('parameter', new StringType)
                        ->addProperty('pointer', new StringType))
                    ->setRequired(['status', 'title', 'detail'])
            ))
            ->setRequired(['errors']);
    }

    private static function forceJsonApiErrorContent(OpenApi $openApi): void
    {
        $schema = Schema::fromType(self::errorDocument());

        foreach ($openApi->components->responses as $response) {
            if (! is_numeric($response->code) || (int) $response->code < 400) {
                continue;
            }

            $response->content = [];
            $response->setContent('application/vnd.api+json', $schema);
        }

        foreach ($openApi->paths as $path) {
            foreach ($path->operations as $operation) {
                foreach ($operation->responses ?? [] as $response) {
                    if (! $response instanceof Response || ! is_numeric($response->code) || (int) $response->code < 400) {
                        continue;
                    }

                    $response->content = [];
                    $response->setContent('application/vnd.api+json', $schema);
                }
            }
        }
    }

    private static function resourceDocument(ObjectType $resource, bool $paginated): ObjectType
    {
        $document = (new ObjectType)
            ->addProperty('data', (new ArrayType)->setItems($resource))
            ->setRequired(['data']);

        if ($paginated) {
            $document
                ->addProperty('links', (new ObjectType)
                    ->addProperty('first', (new StringType)->nullable(true))
                    ->addProperty('last', (new StringType)->nullable(true))
                    ->addProperty('prev', (new StringType)->nullable(true))
                    ->addProperty('next', (new StringType)->nullable(true)))
                ->addProperty('meta', (new ObjectType)
                    ->addProperty('current_page', new IntegerType)
                    ->addProperty('from', (new IntegerType)->nullable(true))
                    ->addProperty('last_page', new IntegerType)
                    ->addProperty('per_page', new IntegerType)
                    ->addProperty('to', (new IntegerType)->nullable(true))
                    ->addProperty('total', new IntegerType));
        }

        return $document;
    }

    /**
     * @param  array<string, Type>  $attributes
     */
    private static function attributes(array $attributes): ObjectType
    {
        $schema = new ObjectType;

        foreach ($attributes as $name => $type) {
            $schema->addProperty($name, $type);
        }

        return $schema;
    }

    /**
     * @return array<string, Type>
     */
    private static function movieAttributes(): array
    {
        return [
            'num' => new IntegerType,
            'name' => new StringType,
            'stream_type' => new StringType,
            'stream_id' => new IntegerType,
            'stream_icon' => new StringType,
            'rating' => new StringType,
            'rating_5based' => new NumberType,
            'added' => (new StringType)->format('date-time'),
            'is_adult' => new BooleanType,
            'category_id' => (new StringType)->nullable(true),
            'container_extension' => new StringType,
            'custom_sid' => (new StringType)->nullable(true),
            'direct_source' => (new StringType)->nullable(true),
            'created_at' => (new StringType)->format('date-time'),
            'updated_at' => (new StringType)->format('date-time'),
            'vod_info' => self::vodInfoAttributes()->nullable(true),
        ];
    }

    private static function vodInfoAttributes(): ObjectType
    {
        return (new ObjectType)
            ->addProperty('vodId', new IntegerType)
            ->addProperty('movieImage', new StringType)
            ->addProperty('tmdbId', new StringType)
            ->addProperty('backdrop', new StringType)
            ->addProperty('youtubeTrailer', new StringType)
            ->addProperty('genre', new StringType)
            ->addProperty('plot', new StringType)
            ->addProperty('cast', new StringType)
            ->addProperty('rating', new StringType)
            ->addProperty('director', new StringType)
            ->addProperty('releaseDate', new StringType)
            ->addProperty('backdropPath', (new ArrayType)->setItems(new StringType))
            ->addProperty('durationSecs', new IntegerType)
            ->addProperty('duration', new StringType)
            ->addProperty('video', new ObjectType)
            ->addProperty('audio', new ObjectType)
            ->addProperty('bitrate', new IntegerType)
            ->addProperty('movie', (new ObjectType)
                ->addProperty('streamId', new IntegerType)
                ->addProperty('name', new StringType)
                ->addProperty('added', new StringType)
                ->addProperty('categoryId', new StringType)
                ->addProperty('containerExtension', new StringType)
                ->addProperty('customSid', new StringType)
                ->addProperty('directSource', new StringType));
    }

    /**
     * @return array<string, Type>
     */
    private static function seriesAttributes(): array
    {
        return [
            'num' => new IntegerType,
            'name' => new StringType,
            'series_id' => new IntegerType,
            'cover' => (new StringType)->nullable(true),
            'plot' => (new StringType)->nullable(true),
            'cast' => (new StringType)->nullable(true),
            'director' => (new StringType)->nullable(true),
            'genre' => (new StringType)->nullable(true),
            'backdrop_path' => (new ArrayType)->setItems(new StringType),
            'releaseDate' => (new StringType)->nullable(true),
            'last_modified' => (new StringType)->format('date-time')->nullable(true),
            'category_id' => (new StringType)->nullable(true),
            'rating' => (new NumberType)->nullable(true),
            'rating_5based' => (new NumberType)->nullable(true),
            'created_at' => (new StringType)->format('date-time'),
            'updated_at' => (new StringType)->format('date-time'),
            'seasons' => (new ArrayType)->setItems(new StringType)->nullable(true),
            'episodes' => (new ObjectType)->nullable(true),
        ];
    }

    /**
     * @return array<string, Type>
     */
    private static function downloadAttributes(): array
    {
        return [
            'id' => new IntegerType,
            'gid' => new StringType,
            'media_id' => new IntegerType,
            'media_type' => new StringType,
            'downloadable_id' => new IntegerType,
            'user_id' => (new IntegerType)->nullable(true),
            'desired_paused' => new BooleanType,
            'canceled_at' => (new StringType)->format('date-time')->nullable(true),
            'cancel_delete_partial' => new BooleanType,
            'last_error_code' => (new IntegerType)->nullable(true),
            'last_error_message' => (new StringType)->nullable(true),
            'retry_attempt' => new IntegerType,
            'retry_next_at' => (new StringType)->format('date-time')->nullable(true),
            'download_files' => (new ArrayType)->setItems(new StringType)->nullable(true),
            'created_at' => (new StringType)->format('date-time'),
            'updated_at' => (new StringType)->format('date-time'),
            'media' => new ObjectType,
            'owner' => (new ObjectType)->nullable(true),
            'downloadStatus' => self::downloadStatusAttributes()->nullable(true),
            'season' => (new IntegerType)->nullable(true),
            'episode' => (new IntegerType)->nullable(true),
        ];
    }

    private static function downloadStatusAttributes(): ObjectType
    {
        return (new ObjectType)
            ->addProperty('gid', new StringType)
            ->addProperty('status', new StringType)
            ->addProperty('totalLength', new StringType)
            ->addProperty('completedLength', new StringType)
            ->addProperty('downloadSpeed', new StringType)
            ->addProperty('errorCode', (new StringType)->nullable(true))
            ->addProperty('errorMessage', (new StringType)->nullable(true))
            ->addProperty('dir', (new StringType)->nullable(true))
            ->addProperty('files', (new ArrayType)->setItems(new ObjectType));
    }

    /**
     * @return array<string, Type>
     */
    private static function seriesMonitorAttributes(): array
    {
        return [
            'id' => new IntegerType,
            'series_id' => new IntegerType,
            'series_name' => (new StringType)->nullable(true),
            'series_cover' => (new StringType)->nullable(true),
            'enabled' => new BooleanType,
            'timezone' => new StringType,
            'schedule_type' => new StringType,
            'schedule_daily_time' => (new StringType)->nullable(true),
            'schedule_weekly_days' => (new ArrayType)->setItems(new IntegerType),
            'schedule_weekly_time' => (new StringType)->nullable(true),
            'monitored_seasons' => (new ArrayType)->setItems(new IntegerType),
            'per_run_cap' => new IntegerType,
            'next_run_at' => (new StringType)->format('date-time')->nullable(true),
            'last_attempt_at' => (new StringType)->format('date-time')->nullable(true),
            'last_attempt_status' => (new StringType)->nullable(true),
            'last_successful_check_at' => (new StringType)->format('date-time')->nullable(true),
            'run_now_available_at' => (new StringType)->format('date-time')->nullable(true),
        ];
    }
}
