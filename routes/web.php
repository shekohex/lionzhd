<?php

declare(strict_types=1);

use App\Http\Controllers\DiscoverController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SeriesController;
use App\Http\Controllers\VodStreamController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [WelcomeController::class, 'index'])->name('home');

Route::middleware(['auth', 'verified'])->group(static function (): void {
    Route::get('discover', [DiscoverController::class, 'index'])->name('discover');
    Route::controller(SearchController::class)->group(static function (): void {
        Route::get('search', 'full')->name('search');
        Route::get('search/lightweight', 'lightweight')->name('search.lightweight');
    });

    Route::controller(VodStreamController::class)->group(static function (): void {
        Route::get('movies', 'index')->name('movies');
        Route::get('movies/{model}', 'show')->whereNumber('model')->name('movies.show');
    });

    Route::controller(SeriesController::class)->group(static function (): void {
        Route::get('series', 'index')->name('series');
        Route::get('series/{model}', 'show')->whereNumber('model')->name('series.show');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
