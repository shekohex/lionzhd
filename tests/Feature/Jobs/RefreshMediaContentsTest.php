<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Http\Integrations\LionzTv\Requests\GetSeriesRequest;
use App\Http\Integrations\LionzTv\Requests\GetVodStreamsRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Jobs\RefreshMediaContents;
use App\Models\XtreamCodesConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->bind(XtreamCodesConfig::class, static fn () => new XtreamCodesConfig(
        [
            'host' => 'http://test.api',
            'port' => 80,
            'username' => 'test_user',
            'password' => 'test_pass',
        ]
    ));

    app()->bind(XtreamCodesConnector::class, function (): XtreamCodesConnector {
        $connector = new XtreamCodesConnector(app(XtreamCodesConfig::class));

        return $connector->withMockClient(new MockClient([
            GetSeriesRequest::class => MockResponse::make([], 200),
            GetVodStreamsRequest::class => MockResponse::make([], 200),
        ]));
    });
});

test('it works', function (): void {
    $job = app()->make(RefreshMediaContents::class);
    $job->withFakeQueueInteractions()
        ->assertNotFailed()->handle();
});

test('it skips search operations when search backend is unavailable', function (): void {
    Config::set('scout.driver', 'meilisearch');
    Config::set('scout.meilisearch.host', 'http://127.0.0.1:59999');

    $job = app()->make(RefreshMediaContents::class);
    $job->withFakeQueueInteractions()
        ->assertNotFailed()->handle();
});

test('it bumps xtream dto cache namespace after sync', function (): void {
    Cache::store()->forever(XtreamCodesConnector::DTO_CACHE_NAMESPACE_KEY, 4);

    $job = app()->make(RefreshMediaContents::class);
    $job->withFakeQueueInteractions()
        ->assertNotFailed()->handle();

    expect(Cache::store()->get(XtreamCodesConnector::DTO_CACHE_NAMESPACE_KEY))->toBe(5);
});
