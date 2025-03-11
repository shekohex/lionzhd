<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Client\XtreamCodesClient;
use App\Exceptions\UnauthorizedAccessException;
use App\Models\HttpClientConfig;
use App\Models\XtreamCodesConfig;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class XtreamCodesClientTest extends BaseTestCase
{
    private XtreamCodesClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        // Bind mock configs to the container
        $this->app->bind(
            XtreamCodesConfig::class,
            static fn () => new XtreamCodesConfig(
                [
                    'host' => 'http://test.api',
                    'port' => 80,
                    'username' => 'test_user',
                    'password' => 'test_pass',
                ]
            )
        );

        $this->app->bind(
            HttpClientConfig::class,
            static fn () => new HttpClientConfig(
                [
                    'user_agent' => 'TestUserAgent',
                    'timeout' => 30,
                    'connect_timeout' => 30,
                    'verify_ssl' => false,
                    'default_headers' => ['X-Custom-Header' => 'value'],
                ]
            )
        );

        // Resolve the client from the container
        $this->client = $this->app->make(XtreamCodesClient::class);
    }

    public function test_authenticate_successful(): void
    {
        // Arrange
        $expectedResponse = ['user_info' => ['status' => 'Active']];

        Http::fake(
            [
                'http://test.api/player_api.php*' => Http::response($expectedResponse, 200),
            ]
        );

        // Act
        $result = $this->client->authenticate();

        // Assert
        $this->assertEquals($expectedResponse, $result);
        Http::assertSent(
            static fn ($request) => $request->url() === 'http://test.api/player_api.php?username=test_user&password=test_pass'
        );
    }

    public function test_authenticate_throws_unauthorized_exception(): void
    {
        // Arrange
        Http::fake(
            [
                'http://test.api/player_api.php*' => Http::response('Not Found', 404),
            ]
        );

        // Assert & Act
        $this->expectException(UnauthorizedAccessException::class);
        $this->client->authenticate();
    }

    public function test_authenticate_throws_connection_exception(): void
    {
        // Arrange
        Http::fake(
            [
                'http://test.api/player_api.php*' => static fn () => throw new ConnectionException,
            ]
        );

        // Assert & Act
        $this->expectException(ConnectionException::class);
        $this->client->authenticate();
    }

    public function test_series(): void
    {
        // Arrange
        $expectedResponse = json_decode(file_get_contents(__DIR__.'/../../fixtures/get_series.json'));

        Http::fake(
            [
                'http://test.api/player_api.php*' => Http::response($expectedResponse, 200),
            ]
        );

        // Act
        $result = $this->client->series()->all();

        // Assert
        $this->assertCount(3, $result);
        $this->assertEquals('044-Friends', $result[0]['name']);
        $this->assertEquals('Batwoman', $result[1]['name']);
    }

    public function test_series_throws_connection_exception(): void
    {
        // Arrange
        Http::fake(
            [
                'http://test.api/player_api.php*' => static fn () => throw new ConnectionException,
            ]
        );

        // Assert & Act
        $this->expectException(ConnectionException::class);
        $this->client->series();
    }

    public function test_vod_streams(): void
    {
        // Arrange
        $expectedResponse = json_decode(file_get_contents(__DIR__.'/../../fixtures/get_vod_streams.json'));

        Http::fake(
            [
                'http://test.api/player_api.php*' => Http::response($expectedResponse, 200),
            ]
        );

        // Act
        $result = $this->client->vodStreams()->all();

        // Assert
        $this->assertCount(3, $result);
        $this->assertEquals('Heart Eyes (2025) 4k', $result[0]['name']);
        $this->assertEquals('WWE Monday Night Raw 03 03 2025', $result[1]['name']);
    }

    public function test_vod_streams_throws_connection_exception(): void
    {
        // Arrange
        Http::fake(
            [
                'http://test.api/player_api.php*' => static fn () => throw new ConnectionException,
            ]
        );

        // Assert & Act
        $this->expectException(ConnectionException::class);
        $this->client->vodStreams();
    }
}
