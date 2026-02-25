<?php

declare(strict_types=1);

use App\Models\User;

it('forbids external members from schedules settings', function (): void {
    $user = User::factory()->memberExternal()->make();

    $response = $this->actingAs($user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->get('/settings/schedules');

    $response->assertForbidden();
});

it('allows internal members to access schedules settings', function (): void {
    $user = User::factory()->memberInternal()->make();

    $response = $this->actingAs($user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->get('/settings/schedules');

    $response->assertOk();
    $response->assertHeader('X-Inertia', 'true');
    $response->assertJsonPath('component', 'settings/schedules');
});

it('allows admins to access schedules settings', function (): void {
    $user = User::factory()->admin()->make();

    $response = $this->actingAs($user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->get('/settings/schedules');

    $response->assertOk();
    $response->assertHeader('X-Inertia', 'true');
    $response->assertJsonPath('component', 'settings/schedules');
});
