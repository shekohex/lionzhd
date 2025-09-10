<?php

declare(strict_types=1);

use App\Http\Controllers\DirectDownloadController;
use App\Http\Controllers\DiscoverController;
use App\Http\Controllers\LightweightSearchController;
use App\Http\Controllers\MediaDownloadsController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Series\SeriesCacheController;
use App\Http\Controllers\Series\SeriesController;
use App\Http\Controllers\Series\SeriesDownloadController;
use App\Http\Controllers\Series\SeriesWatchlistController;
use App\Http\Controllers\VodStream\VodStreamCacheController;
use App\Http\Controllers\VodStream\VodStreamController;
use App\Http\Controllers\VodStream\VodStreamDownloadController;
use App\Http\Controllers\VodStream\VodStreamWatchlistController;
use App\Http\Controllers\WatchlistController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [WelcomeController::class, 'index'])->name('home');

Route::middleware(['auth', 'verified'])->group(static function (): void {

    Route::get('/discover', [DiscoverController::class, 'index'])->name('discover');

    Route::post('/search', [LightweightSearchController::class, 'show'])->name('search.lightweight');
    Route::get('/search', [SearchController::class, 'show'])->name('search.full');

    Route::controller(VodStreamController::class)->prefix('movies')->group(static function (): void {
        Route::get('/', 'index')->name('movies');
        Route::get('{model}', 'show')->whereNumber('model')->name('movies.show');
    });
    Route::prefix('movies')->delete('{model}/cache', [VodStreamCacheController::class, 'destroy'])->whereNumber('model')->name('movies.cache');
    Route::controller(VodStreamWatchlistController::class)->prefix('movies')->group(static function (): void {
        Route::post('{model}/watchlist', 'store')->whereNumber('model')->name('movies.watchlist');
        Route::delete('{model}/watchlist', 'destroy')->whereNumber('model')->name('movies.watchlist.destroy');
    });
    Route::controller(VodStreamDownloadController::class)->prefix('movies')->group(static function (): void {
        Route::get('{model}/download', 'create')->whereNumber('model')->name('movies.download');
        Route::get('{model}/direct', 'direct')->whereNumber('model')->name('movies.direct');
    });

    Route::controller(SeriesController::class)->prefix('series')->group(static function (): void {
        Route::get('/', 'index')->name('series');
        Route::get('{model}', 'show')->whereNumber('model')->name('series.show');
    });

    Route::controller(SeriesCacheController::class)->prefix('series')->group(static function (): void {
        Route::delete('{model}/cache', 'destroy')->whereNumber('model')->name('series.cache');
    });
    Route::controller(SeriesWatchlistController::class)->prefix('series')->group(static function (): void {
        Route::post('{model}/watchlist', 'store')->whereNumber('model')->name('series.watchlist');
        Route::delete('{model}/watchlist', 'destroy')->whereNumber('model')->name('series.watchlist.destroy');
    });
    Route::controller(SeriesDownloadController::class)->prefix('series')->group(static function (): void {
        Route::get('{model}/{season}/{episode}/download', 'create')
            ->whereNumber('model')
            ->whereNumber('season')
            ->whereNumber('episode')
            ->name('series.download.single');
        Route::post('{model}/download', 'store')->whereNumber('model')->name('series.download.batch');
        Route::get('{model}/{season}/{episode}/direct', 'direct')
            ->whereNumber('model')
            ->whereNumber('season')
            ->whereNumber('episode')
            ->name('series.direct.single');
        Route::post('{model}/direct.txt', 'batchDirectTxt')
            ->whereNumber('model')
            ->name('series.direct.batch');
    });

    Route::controller(WatchlistController::class)->prefix('watchlist')->group(static function (): void {
        Route::get('/', 'index')->name('watchlist');
        Route::post('/', 'store')->name('watchlist.store');
        Route::delete('{id}', 'destroy')->whereNumber('id')->name('watchlist.destroy');
    });

    Route::controller(MediaDownloadsController::class)->prefix('downloads')->group(static function (): void {
        Route::get('/', 'index')->name('downloads');
        Route::patch('{model}', 'edit')->whereNumber('model')->name('downloads.edit');
        Route::delete('{model}', 'destroy')->whereNumber('model')->name('downloads.destroy');
    });
});

Route::get('/dl/{token}', [DirectDownloadController::class, 'show'])
    ->middleware('signed')
    ->name('direct.resolve');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
