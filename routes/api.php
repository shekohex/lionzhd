<?php

declare(strict_types=1);

use App\Http\Controllers\Api\CategoriesController;
use App\Http\Controllers\Api\DiscoverController;
use App\Http\Controllers\Api\LightweightSearchController;
use App\Http\Controllers\Api\MovieCacheController;
use App\Http\Controllers\Api\MovieDirectLinkController;
use App\Http\Controllers\Api\MovieDownloadController;
use App\Http\Controllers\Api\MoviesController;
use App\Http\Controllers\Api\MovieWatchlistController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SearchController;
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
    });
