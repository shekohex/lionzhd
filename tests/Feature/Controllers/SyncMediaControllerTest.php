<?php

declare(strict_types=1);

use App\Jobs\RefreshMediaContents;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('allows admins to queue media sync job from settings endpoint', function (): void {
    Queue::fake();

    $user = User::factory()->admin()->make();

    $response = $this->actingAs($user)->patch(route('syncmedia.update'));

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Media Library sync queued successfully.');

    Queue::assertPushed(RefreshMediaContents::class);
});

it('forbids members from queueing media sync job from settings endpoint', function (): void {
    Queue::fake();

    $user = User::factory()->memberInternal()->make();

    $response = $this->actingAs($user)->patch(route('syncmedia.update'));

    $response->assertForbidden();
    Queue::assertNothingPushed();
});

it('renders inertia forbidden page for unauthorized member settings access', function (): void {
    $user = User::factory()->memberExternal()->make();

    $response = $this->actingAs($user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->patch(route('syncmedia.update'));

    $response->assertForbidden();
    $response->assertHeader('X-Inertia', 'true');
    $response->assertJsonPath('component', 'errors/forbidden');
    $response->assertJsonPath('props.reason', 'Admin-only');
});
