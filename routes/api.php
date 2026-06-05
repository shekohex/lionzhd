<?php

declare(strict_types=1);

use App\Http\Controllers\Api\CategoriesController;
use App\Http\Controllers\Api\CategoryPreferencesController;
use App\Http\Controllers\Api\DiscoverController;
use App\Http\Controllers\Api\DownloadsController;
use App\Http\Controllers\Api\LightweightSearchController;
use App\Http\Controllers\Api\MovieCacheController;
use App\Http\Controllers\Api\MovieDirectLinkController;
use App\Http\Controllers\Api\MovieDownloadController;
use App\Http\Controllers\Api\MoviesController;
use App\Http\Controllers\Api\MovieWatchlistController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SeriesCacheController;
use App\Http\Controllers\Api\SeriesController;
use App\Http\Controllers\Api\SeriesDirectLinksTextController;
use App\Http\Controllers\Api\SeriesDownloadController;
use App\Http\Controllers\Api\SeriesEpisodeDirectLinkController;
use App\Http\Controllers\Api\SeriesEpisodeDownloadController;
use App\Http\Controllers\Api\SeriesMonitoringBackfillController;
use App\Http\Controllers\Api\SeriesMonitoringController;
use App\Http\Controllers\Api\SeriesMonitoringRunNowController;
use App\Http\Controllers\Api\SeriesWatchlistController;
use App\Http\Controllers\Api\TokensController;
use App\Http\Controllers\Api\WatchlistController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['AcceptJsonApi', 'auth:sanctum', 'throttle:api'])
    ->group(function (): void {
        Route::get('movies', [MoviesController::class, 'index'])
            ->middleware('abilities:read')
            ->name('api.v1.movies.index');

        Route::get('movies/{movie:stream_id}', [MoviesController::class, 'show'])
            ->middleware('abilities:read')
            ->name('api.v1.movies.show');

        Route::post('movies/{movie:stream_id}/watchlist', [MovieWatchlistController::class, 'store'])
            ->middleware('abilities:read')
            ->name('api.v1.movies.watchlist.store');

        Route::delete('movies/{movie:stream_id}/watchlist', [MovieWatchlistController::class, 'destroy'])
            ->middleware('abilities:read')
            ->name('api.v1.movies.watchlist.destroy');

        Route::post('movies/{movie:stream_id}/download', [MovieDownloadController::class, 'store'])
            ->middleware('abilities:server-download')
            ->name('api.v1.movies.download');

        Route::get('movies/{movie:stream_id}/direct', [MovieDirectLinkController::class, 'show'])
            ->middleware('abilities:read')
            ->name('api.v1.movies.direct');

        Route::delete('movies/{movie:stream_id}/cache', [MovieCacheController::class, 'destroy'])
            ->middleware('abilities:admin')
            ->name('api.v1.movies.cache.destroy');

        Route::get('series', [SeriesController::class, 'index'])
            ->middleware('abilities:read')
            ->name('api.v1.series.index');

        Route::get('series/{series:series_id}', [SeriesController::class, 'show'])
            ->middleware('abilities:read')
            ->name('api.v1.series.show');

        Route::post('series/{series:series_id}/watchlist', [SeriesWatchlistController::class, 'store'])
            ->middleware('abilities:read')
            ->name('api.v1.series.watchlist.store');

        Route::delete('series/{series:series_id}/watchlist', [SeriesWatchlistController::class, 'destroy'])
            ->middleware('abilities:read')
            ->name('api.v1.series.watchlist.destroy');

        Route::get('series/{series:series_id}/monitoring', [SeriesMonitoringController::class, 'show'])
            ->middleware('abilities:read')
            ->name('api.v1.series.monitoring.show');

        Route::post('series/{series:series_id}/monitoring', [SeriesMonitoringController::class, 'store'])
            ->middleware('abilities:monitoring:admin')
            ->name('api.v1.series.monitoring.store');

        Route::patch('series/{series:series_id}/monitoring', [SeriesMonitoringController::class, 'update'])
            ->middleware('abilities:monitoring:admin')
            ->name('api.v1.series.monitoring.update');

        Route::delete('series/{series:series_id}/monitoring', [SeriesMonitoringController::class, 'destroy'])
            ->middleware('abilities:monitoring:admin')
            ->name('api.v1.series.monitoring.destroy');

        Route::post('series/{series:series_id}/monitoring/run-now', SeriesMonitoringRunNowController::class)
            ->middleware('abilities:monitoring:admin')
            ->name('api.v1.series.monitoring.run-now');

        Route::post('series/{series:series_id}/monitoring/backfill', SeriesMonitoringBackfillController::class)
            ->middleware('abilities:monitoring:admin')
            ->name('api.v1.series.monitoring.backfill');

        Route::post('series/{series:series_id}/seasons/{season}/episodes/{episode}/download', [SeriesEpisodeDownloadController::class, 'store'])
            ->whereNumber(['season', 'episode'])
            ->middleware('abilities:server-download')
            ->name('api.v1.series.episodes.download');

        Route::post('series/{series:series_id}/download', [SeriesDownloadController::class, 'store'])
            ->middleware('abilities:server-download')
            ->name('api.v1.series.download');

        Route::get('series/{series:series_id}/seasons/{season}/episodes/{episode}/direct', [SeriesEpisodeDirectLinkController::class, 'show'])
            ->whereNumber(['season', 'episode'])
            ->middleware('abilities:read')
            ->name('api.v1.series.episodes.direct');

        Route::post('series/{series:series_id}/direct.txt', [SeriesDirectLinksTextController::class, 'store'])
            ->middleware('abilities:read')
            ->name('api.v1.series.direct.text');

        Route::delete('series/{series:series_id}/cache', [SeriesCacheController::class, 'destroy'])
            ->middleware('abilities:admin')
            ->name('api.v1.series.cache.destroy');

        Route::get('watchlist', [WatchlistController::class, 'index'])
            ->middleware('abilities:read')
            ->name('api.v1.watchlist.index');

        Route::post('watchlist', [WatchlistController::class, 'store'])
            ->middleware('abilities:read')
            ->name('api.v1.watchlist.store');

        Route::delete('watchlist/{watchlist}', [WatchlistController::class, 'destroy'])
            ->middleware('abilities:read')
            ->name('api.v1.watchlist.destroy');

        Route::patch('preferences/categories/{mediaType}', [CategoryPreferencesController::class, 'update'])
            ->whereIn('mediaType', ['movie', 'series'])
            ->middleware('abilities:read')
            ->name('api.v1.preferences.categories.update');

        Route::delete('preferences/categories/{mediaType}', [CategoryPreferencesController::class, 'destroy'])
            ->whereIn('mediaType', ['movie', 'series'])
            ->middleware('abilities:read')
            ->name('api.v1.preferences.categories.destroy');

        Route::get('downloads', [DownloadsController::class, 'index'])
            ->middleware('abilities:read')
            ->name('api.v1.downloads.index');

        Route::patch('downloads/{download}', [DownloadsController::class, 'update'])
            ->middleware('abilities:download-operations')
            ->name('api.v1.downloads.update');

        Route::delete('downloads/{download}', [DownloadsController::class, 'destroy'])
            ->middleware('abilities:download-operations')
            ->name('api.v1.downloads.destroy');

        Route::get('discover', [DiscoverController::class, 'show'])
            ->middleware('abilities:read')
            ->name('api.v1.discover');

        Route::post('search', [SearchController::class, 'store'])
            ->middleware('abilities:read')
            ->name('api.v1.search');

        Route::post('search/lightweight', [LightweightSearchController::class, 'store'])
            ->middleware('abilities:read')
            ->name('api.v1.search.lightweight');

        Route::get('categories', [CategoriesController::class, 'index'])
            ->middleware('abilities:read')
            ->name('api.v1.categories.index');

        Route::get('categories/{category:provider_id}', [CategoriesController::class, 'show'])
            ->middleware('abilities:read')
            ->name('api.v1.categories.show');

        Route::get('me', [ProfileController::class, 'show'])
            ->middleware('abilities:read')
            ->name('api.v1.me');

        Route::get('tokens', [TokensController::class, 'index'])
            ->middleware('abilities:read')
            ->name('api.v1.tokens.index');

        Route::post('tokens', [TokensController::class, 'store'])
            ->middleware('abilities:read')
            ->name('api.v1.tokens.store');

        Route::delete('tokens/{token}', [TokensController::class, 'destroy'])
            ->middleware('abilities:read')
            ->name('api.v1.tokens.destroy');
    });
