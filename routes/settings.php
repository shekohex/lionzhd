<?php

declare(strict_types=1);

use App\Http\Controllers\Settings\Aria2ConfigController;
use App\Http\Controllers\Settings\CategorySyncRunsController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SyncCategoriesController;
use App\Http\Controllers\Settings\SyncMediaController;
use App\Http\Controllers\Settings\UsersController;
use App\Http\Controllers\Settings\XtreamCodeConfigController;
use App\Http\Controllers\AutoEpisodes\AutoEpisodesPauseController;
use App\Http\Controllers\AutoEpisodes\BulkApplySeriesMonitoringPresetController;
use App\Http\Controllers\AutoEpisodes\MonitoringPageController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(static function (): void {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    Route::middleware('can:admin')->group(static function (): void {
        Route::get('settings/users', [UsersController::class, 'index'])->name('users.index');
        Route::patch('settings/users/{user}/subtype', [UsersController::class, 'update'])
            ->defaults('operation', 'subtype')
            ->name('users.subtype.update');

        Route::middleware('can:super-admin')->group(static function (): void {
            Route::patch('settings/users/{user}/role', [UsersController::class, 'update'])
                ->defaults('operation', 'role')
                ->name('users.role.update');
            Route::patch('settings/users/{user}/super-admin', [UsersController::class, 'update'])
                ->defaults('operation', 'transfer-super-admin')
                ->name('users.super-admin.transfer');
        });

        Route::get('settings/xtreamcodes', [XtreamCodeConfigController::class, 'edit'])->name('xtreamcodes.edit');
        Route::patch('settings/xtreamcodes', [XtreamCodeConfigController::class, 'update'])->name('xtreamcodes.update');

        Route::get('settings/aria2', [Aria2ConfigController::class, 'edit'])->name('aria2.edit');
        Route::patch('settings/aria2', [Aria2ConfigController::class, 'update'])->name('aria2.update');

        Route::get('settings/syncmedia', [SyncMediaController::class, 'edit'])->name('syncmedia.edit');
        Route::patch('settings/syncmedia', [SyncMediaController::class, 'update'])->name('syncmedia.update');

        Route::get('settings/synccategories', [SyncCategoriesController::class, 'edit'])->name('synccategories.edit');
        Route::patch('settings/synccategories', [SyncCategoriesController::class, 'update'])->name('synccategories.update');
        Route::get('settings/synccategories/history', [CategorySyncRunsController::class, 'index'])->name('synccategories.history');
    });

    Route::get('settings/schedules', [MonitoringPageController::class, 'index'])->name('schedules');

    Route::middleware('can:auto-download-schedules')->prefix('settings/schedules')->group(static function (): void {
        Route::patch('bulk-apply', BulkApplySeriesMonitoringPresetController::class)
            ->name('schedules.bulk-apply');
        Route::patch('pause', [AutoEpisodesPauseController::class, 'update'])
            ->name('schedules.pause');
    });

    Route::get('settings/appearance', static fn () => Inertia::render('settings/appearance'))->name('appearance');
});
