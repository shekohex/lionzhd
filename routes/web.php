<?php

declare(strict_types=1);

use App\Http\Controllers\DiscoverController;
use App\Http\Controllers\MediaDownloadsController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SeriesController;
use App\Http\Controllers\VodStreamController;
use App\Http\Controllers\WatchlistController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::controller(WelcomeController::class)->prefix('/')->group(static function (): void {
    Route::get('/', 'index')->name('home');
    Route::post('/search', 'search')->name('home.search');
});

Route::middleware(['auth', 'verified'])->group(static function (): void {

    Route::controller(DiscoverController::class)->prefix('discover')->group(static function (): void {
        Route::get('/', 'index')->name('discover');
    });

    Route::controller(SearchController::class)->prefix('search')->group(static function (): void {
        Route::get('/', 'full')->name('search.full');
    });

    Route::controller(VodStreamController::class)->prefix('movies')->group(static function (): void {
        Route::get('/', 'index')->name('movies');
        Route::get('{model}', 'show')->whereNumber('model')->name('movies.show');
        Route::delete('{model}/cache', 'forgetCache')->whereNumber('model')->name('movies.cache');
        Route::get('{model}/download', 'download')->whereNumber('model')->name('movies.download');

        Route::post('{model}/watchlist', 'addToWatchlist')->whereNumber('model')->name('movies.watchlist');
        Route::delete('{model}/watchlist', 'removeFromWatchlist')->whereNumber('model')->name('movies.watchlist');
    });

    Route::controller(SeriesController::class)->prefix('series')->group(static function (): void {
        Route::get('/', 'index')->name('series');
        Route::get('{model}', 'show')->whereNumber('model')->name('series.show');
        Route::delete('{model}/cache', 'forgetCache')->whereNumber('model')->name('series.cache');

        Route::get('{model}/{season}/{episode}/download', 'download')
            ->whereNumber('model')
            ->whereNumber('season')
            ->whereNumber('episode')
            ->name('series.download.single');

        Route::post('{model}/download', 'downloadBatch')->whereNumber('model')->name('series.download.batch');

        Route::post('{model}/watchlist', 'addToWatchlist')->whereNumber('model')->name('series.watchlist');
        Route::delete('{model}/watchlist', 'removeFromWatchlist')->whereNumber('model')->name('series.watchlist');
    });

    Route::controller(WatchlistController::class)->prefix('watchlist')->group(static function (): void {
        Route::get('/', 'index')->name('watchlist');
        Route::post('/', 'store')->name('watchlist.store');
        Route::delete('{id}', 'destroy')->whereNumber('id')->name('watchlist.destroy');
    });

    Route::controller(MediaDownloadsController::class)->prefix('downloads')->group(static function (): void {
        Route::get('/', 'index')->name('downloads');
        Route::delete('{id}', 'destroy')->whereNumber('id')->name('downloads.destroy');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
