<?php

declare(strict_types=1);

namespace App\Http\Integrations\LionzTv\Requests;

use App\Http\Integrations\LionzTv\Responses\SeriesInformation;
use Illuminate\Support\Facades\Cache;
use Saloon\CachePlugin\Contracts\Cacheable;
use Saloon\CachePlugin\Contracts\Driver;
use Saloon\CachePlugin\Drivers\LaravelCacheDriver;
use Saloon\CachePlugin\Traits\HasCaching;
use Saloon\Enums\Method;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;

final class GetSeriesInfoRequest extends Request implements Cacheable
{
    use HasCaching;

    /**
     * The HTTP method of the request
     */
    protected Method $method = Method::GET;

    public function __construct(private int $stream_id) {}

    /**
     * Forcefully forget the cache for this request without making a new request
     */
    public function forceForgetCache(): void
    {
        $cacheDriver = $this->resolveCacheDriver();
        $cacheDriver->delete($this->formatCacheKey());
    }

    /**
     * The endpoint for the request
     */
    public function resolveEndpoint(): string
    {
        return '/player_api.php';
    }

    public function createDtoFromResponse(Response $response): SeriesInformation
    {
        $data = $response->json();

        return SeriesInformation::fromJson($this->stream_id, $data);
    }

    public function resolveCacheDriver(): Driver
    {
        return new LaravelCacheDriver(Cache::store());
    }

    public function cacheExpiryInSeconds(): int
    {
        return 1 * 24 * 60 * 60; // 1-day in seconds
    }

    protected function cacheKey(PendingRequest $pendingRequest): string
    {
        return $this->formatCacheKey();
    }

    protected function defaultQuery(): array
    {
        return [
            'action' => 'get_series_info',
            'series_id' => $this->stream_id,
        ];
    }

    private function formatCacheKey(): string
    {
        return 'series_info_'.$this->stream_id;
    }
}
