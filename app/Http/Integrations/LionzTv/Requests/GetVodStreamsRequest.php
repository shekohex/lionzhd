<?php

declare(strict_types=1);

namespace App\Http\Integrations\LionzTv\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

final class GetVodStreamsRequest extends Request
{
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
     * @return array<int, array<string, mixed>>
     */
    public function createDtoFromResponse(Response $response): array
    {
        $data = $response->json();

        if (! is_array($data)) {
            return [];
        }

        $vodStreams = [];

        foreach ($data as $item) {
            if (! is_array($item)) {
                continue;
            }

            $vodStreams[] = $item;
        }

        return $vodStreams;
    }

    protected function defaultQuery(): array
    {
        return [
            'action' => 'get_vod_streams',
        ];
    }
}
