<?php

declare(strict_types=1);

use App\Http\Controllers\Api\CategoriesController;
use App\Http\Controllers\Api\DiscoverController;
use App\Http\Controllers\Api\LightweightSearchController;
use App\Http\Controllers\Api\MoviesController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SearchController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['AcceptJsonApi', 'auth:sanctum', 'throttle:api'])
    ->group(function (): void {
        Route::get('movies', [MoviesController::class, 'index'])
            ->middleware('abilities:read')
            ->name('api.v1.movies.index');

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
