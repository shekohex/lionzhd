<?php

declare(strict_types=1);

namespace App\Http\Integrations\LionzTv\Requests;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Saloon\CachePlugin\Contracts\Cacheable;
use Saloon\CachePlugin\Contracts\Driver;
use Saloon\CachePlugin\Drivers\LaravelCacheDriver;
use Saloon\CachePlugin\Traits\HasCaching;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

final class GetSeriesRequest extends Request implements Cacheable
{
    use HasCaching;

    /**
     * The HTTP method of the request
     */
    protected Method $method = Method::GET;

    /**
     * The endpoint for the request
     */
    public function resolveEndpoint(): string
    {
        return '/player_api.php';
    }

    /**
     * The DTO class to be used for the response
     *
     * @return Collection<array<string, mixed>>
     */
    public function createDtoFromResponse(Response $response): Collection
    {
        $data = $response->json();

        /** @var Collection<array<string, mixed>> $collection */
        $collection = collect($data);

        return $collection
            ->map(function (array $it): array {
                $it['backdrop_path'] = json_encode($it['backdrop_path']);

                return $it;
            });
    }

    public function resolveCacheDriver(): Driver
    {
        return new LaravelCacheDriver(Cache::store());
    }

    public function cacheExpiryInSeconds(): int
    {
        return 12 * 60 * 60; // 12 hours in seconds
    }

    protected function defaultQuery(): array
    {
        return [
            'action' => 'get_series',
        ];
    }
}
