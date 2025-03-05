<?php

namespace Tests\Feature\Jobs;

use App\Jobs\RefreshMediaContents;
use App\Models\HttpClientConfig;
use App\Models\XtreamCodeConfig;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RefreshMediaContentsTest extends TestCase
{
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
            ->assertNotFailed()
            ->handle();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Bind mock configs to the container
        $this->app->bind(XtreamCodeConfig::class, function () {
            return new XtreamCodeConfig(
                [
                    'host' => 'http://test.api',
                    'port' => 80,
                    'username' => 'test_user',
                    'password' => 'test_pass',
                ]
            );
        });

        $this->app->bind(HttpClientConfig::class, function () {
            return new HttpClientConfig(
                [
                    'user_agent' => 'TestUserAgent',
                    'timeout' => 30,
                    'connect_timeout' => 30,
                    'verify_ssl' => false,
                    'default_headers' => ['X-Custom-Header' => 'value'],
                ]
            );
        });
    }
}
