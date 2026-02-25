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
