<?php

declare(strict_types=1);

use App\Http\Controllers\OAuth\ApproveAuthorizationController;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Laravel\Passport\Http\Controllers\AuthorizationController;
use Laravel\Passport\Http\Controllers\DenyAuthorizationController;

Route::post('token', [AccessTokenController::class, 'issueToken'])
    ->middleware('throttle')
    ->name('token');

Route::get('authorize', [AuthorizationController::class, 'authorize'])
    ->middleware('web')
    ->name('authorizations.authorize');

Route::middleware(['web', 'auth:web'])->group(static function (): void {
    Route::post('authorize', ApproveAuthorizationController::class)
        ->name('authorizations.approve');

    Route::delete('authorize', [DenyAuthorizationController::class, 'deny'])
        ->name('authorizations.deny');
});
