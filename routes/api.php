<?php

declare(strict_types=1);

use App\Http\Controllers\Api\MoviesController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['AcceptJsonApi', 'auth:sanctum', 'throttle:api'])
    ->group(function (): void {
        Route::get('movies', [MoviesController::class, 'index'])->name('api.v1.movies.index');

        Route::get('me', [ProfileController::class, 'show'])->name('api.v1.me');
    });
