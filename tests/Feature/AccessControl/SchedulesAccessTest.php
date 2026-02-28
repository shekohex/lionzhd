<?php

declare(strict_types=1);

use App\Models\User;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Request;

it('allows external members to access schedules settings', function (): void {
    $user = User::factory()->memberExternal()->make();

    $response = $this->actingAs($user)
        ->withHeaders(schedulesInertiaHeaders())
        ->get('/settings/schedules');

    $response->assertOk();
    $response->assertHeader('X-Inertia', 'true');
    $response->assertJsonPath('component', 'settings/schedules');
});

it('allows internal members to access schedules settings', function (): void {
    $user = User::factory()->memberInternal()->make();

    $response = $this->actingAs($user)
        ->withHeaders(schedulesInertiaHeaders())
        ->get('/settings/schedules');

    $response->assertOk();
    $response->assertHeader('X-Inertia', 'true');
    $response->assertJsonPath('component', 'settings/schedules');
});

it('allows admins to access schedules settings', function (): void {
    $user = User::factory()->admin()->make();

    $response = $this->actingAs($user)
        ->withHeaders(schedulesInertiaHeaders())
        ->get('/settings/schedules');

    $response->assertOk();
    $response->assertHeader('X-Inertia', 'true');
    $response->assertJsonPath('component', 'settings/schedules');
});

function schedulesInertiaHeaders(): array
{
    $version = app(HandleInertiaRequests::class)->version(Request::create('/'));

    return array_filter([
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => $version,
    ], static fn (?string $value): bool => filled($value));
}
