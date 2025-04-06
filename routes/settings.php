<?php

declare(strict_types=1);

use App\Http\Controllers\Settings\Aria2ConfigController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SyncMediaController;
use App\Http\Controllers\Settings\XtreamCodeConfigController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(static function (): void {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('settings/xtreamcodes', [XtreamCodeConfigController::class, 'edit'])->name('xtreamcodes.edit');
    Route::patch('settings/xtreamcodes', [XtreamCodeConfigController::class, 'update'])->name('xtreamcodes.update');

    Route::get('settings/aria2', [Aria2ConfigController::class, 'edit'])->name('aria2.edit');
    Route::patch('settings/aria2', [Aria2ConfigController::class, 'update'])->name('aria2.update');

    Route::get('settings/syncmedia', [SyncMediaController::class, 'edit'])->name('syncmedia.edit');
    Route::patch('settings/syncmedia', [SyncMediaController::class, 'update'])->name('syncmedia.update');

    Route::get('settings/appearance', static fn () => Inertia::render('settings/appearance'))->name('appearance');
});
