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

final class GetVodCategoriesRequest extends Request implements Cacheable
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

        // Ensure data is a collection
        $collection = collect($data);

        return $collection->map(function ($item) {
             // The API returns strings, but we want integers for IDs.
             // Based on player_api.php: "category_id" => $category["id"], "category_name" => $category["category_name"], "parent_id" => 0

             // Ensure we cast to what we expect
             if (isset($item['category_id'])) {
                 $item['category_id'] = (int) $item['category_id'];
             }
             if (isset($item['parent_id'])) {
                 $item['parent_id'] = (int) $item['parent_id'];
             }

             return $item;
        });
    }

    public function resolveCacheDriver(): Driver
    {
        return new LaravelCacheDriver(Cache::store());
    }

    public function cacheExpiryInSeconds(): int
    {
        return 12 * 60 * 60; // 12 hours
    }

    protected function defaultQuery(): array
    {
        return [
            'action' => 'get_vod_categories',
        ];
    }
}
