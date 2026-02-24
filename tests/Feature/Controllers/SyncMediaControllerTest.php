<?php

declare(strict_types=1);

use App\Jobs\RefreshMediaContents;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('queues media sync job from settings endpoint', function (): void {
    Queue::fake();

    $user = User::factory()->make(['id' => 1]);

    $response = $this->actingAs($user)->patch(route('syncmedia.update'));

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Media Library sync queued successfully.');

    Queue::assertPushed(RefreshMediaContents::class);
});
