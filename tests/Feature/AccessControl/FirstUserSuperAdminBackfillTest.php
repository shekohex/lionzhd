<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('promotes first user to super-admin when none exists', function (): void {
    $firstUser = User::factory()->memberExternal()->create();
    $secondUser = User::factory()->memberExternal()->create();

    $migration = require database_path('migrations/2026_02_28_000001_backfill_first_user_super_admin.php');
    $migration->up();

    $firstUser->refresh();
    $secondUser->refresh();

    expect($firstUser->role)->toBe(UserRole::Admin)
        ->and($firstUser->is_super_admin)->toBeTrue()
        ->and($secondUser->role)->toBe(UserRole::Member)
        ->and($secondUser->is_super_admin)->toBeFalse();
});

it('does not override existing super-admin', function (): void {
    $firstUser = User::factory()->memberExternal()->create();
    $existingSuperAdmin = User::factory()->superAdmin()->create();

    $migration = require database_path('migrations/2026_02_28_000001_backfill_first_user_super_admin.php');
    $migration->up();

    $firstUser->refresh();
    $existingSuperAdmin->refresh();

    expect($firstUser->role)->toBe(UserRole::Member)
        ->and($firstUser->is_super_admin)->toBeFalse()
        ->and($existingSuperAdmin->role)->toBe(UserRole::Admin)
        ->and($existingSuperAdmin->is_super_admin)->toBeTrue();
});
