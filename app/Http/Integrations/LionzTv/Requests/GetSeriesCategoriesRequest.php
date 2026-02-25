<?php

declare(strict_types=1);

namespace App\Http\Integrations\LionzTv\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

final class GetSeriesCategoriesRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/player_api.php';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function createDtoFromResponse(Response $response): array
    {
        $data = $response->json();

        if (! is_array($data) || ! array_is_list($data)) {
            return [];
        }

        $categories = [];

        foreach ($data as $item) {
            if (! is_array($item)) {
                continue;
            }

            $categories[] = $item;
        }

        return $categories;
    }

    protected function defaultQuery(): array
    {
        return [
            'action' => 'get_series_categories',
        ];
    }
}
