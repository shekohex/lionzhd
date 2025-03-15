<?php

declare(strict_types=1);

namespace App\Http\Integrations\LionzTv\Requests;

use App\Http\Integrations\LionzTv\Responses\VodInformation;
use DateInterval;
use Illuminate\Support\Facades\Cache;
use Saloon\CachePlugin\Contracts\Cacheable;
use Saloon\CachePlugin\Contracts\Driver;
use Saloon\CachePlugin\Drivers\LaravelCacheDriver;
use Saloon\CachePlugin\Traits\HasCaching;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

final class GetVodInfoRequest extends Request implements Cacheable
{
    use HasCaching;

    /**
     * The HTTP method of the request
     */
    protected Method $method = Method::GET;

    public function __construct(private int $id) {}

    /**
     * The endpoint for the request
     */
    public function resolveEndpoint(): string
    {
        return '/player_api.php';
    }

    public function createDtoFromResponse(Response $response): VodInformation
    {
        $data = $response->json();

        return VodInformation::fromJson($this->id, $data);
    }

    public function resolveCacheDriver(): Driver
    {
        return new LaravelCacheDriver(Cache::store());
    }

    public function cacheExpiryInSeconds(): int
    {
        return DateInterval::createFromDateString('3 hours')->s;
    }

    protected function defaultQuery(): array
    {
        return [
            'action' => 'get_vod_info',
            'vod_id' => $this->id,
        ];
    }
}
