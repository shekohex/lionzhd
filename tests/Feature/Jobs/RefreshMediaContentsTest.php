<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\RefreshMediaContents;
use App\Models\XtreamCodesConfig;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    // Bind mock configs to the container
    app()->bind(XtreamCodesConfig::class, static fn () => new XtreamCodesConfig(
        [
            'host' => 'http://test.api',
            'port' => 80,
            'username' => 'test_user',
            'password' => 'test_pass',
        ]
    ));
});

test('it works', function (): void {
    $expectedResponse = [];

    Http::fake([
        'http://test.api/player_api.php*' => Http::response($expectedResponse, 200),
    ]);
    $job = app()->make(RefreshMediaContents::class);
    $job->withFakeQueueInteractions()
        ->assertNotFailed()->handle();
});
