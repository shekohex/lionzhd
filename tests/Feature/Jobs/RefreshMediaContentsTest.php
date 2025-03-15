<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\RefreshMediaContents;
use App\Models\XtreamCodesConfig;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class RefreshMediaContentsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Bind mock configs to the container
        $this->app->bind(XtreamCodesConfig::class, static fn () => new XtreamCodesConfig(
            [
                'host' => 'http://test.api',
                'port' => 80,
                'username' => 'test_user',
                'password' => 'test_pass',
            ]
        ));
    }

    /**
     * A basic feature test example.
     */
    public function test_it_works(): void
    {
        $expectedResponse = [];

        Http::fake([
            'http://test.api/player_api.php*' => Http::response($expectedResponse, 200),
        ]);
        $job = $this->app->make(RefreshMediaContents::class);
        $job->withFakeQueueInteractions()
            ->assertNotFailed()->handle();
    }
}
