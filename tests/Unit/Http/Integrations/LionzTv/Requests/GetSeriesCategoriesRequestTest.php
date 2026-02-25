<?php

declare(strict_types=1);

use App\Http\Integrations\LionzTv\Requests\GetSeriesCategoriesRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\XtreamCodesConfig;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('returns empty array when series categories payload is not a list', function (): void {
    $request = new GetSeriesCategoriesRequest;
    $connector = (new XtreamCodesConnector(new XtreamCodesConfig([
        'host' => 'http://host.local',
        'port' => 80,
        'username' => 'user',
        'password' => 'secret',
    ])))->withMockClient(new MockClient([
        GetSeriesCategoriesRequest::class => MockResponse::make([
            'category_id' => '1',
            'category_name' => 'Action',
        ], 200),
    ]));

    $response = $connector->send($request);

    expect($request->createDtoFromResponse($response))->toBe([]);
});

it('filters non-array items from series categories payload', function (): void {
    $payload = [
        ['category_id' => '1', 'category_name' => 'Action'],
        false,
        ['category_id' => '2', 'category_name' => 'Drama'],
        999,
        null,
    ];

    $request = new GetSeriesCategoriesRequest;
    $connector = (new XtreamCodesConnector(new XtreamCodesConfig([
        'host' => 'http://host.local',
        'port' => 80,
        'username' => 'user',
        'password' => 'secret',
    ])))->withMockClient(new MockClient([
        GetSeriesCategoriesRequest::class => MockResponse::make($payload, 200),
    ]));

    $response = $connector->send($request);

    expect($request->createDtoFromResponse($response))->toBe([
        ['category_id' => '1', 'category_name' => 'Action'],
        ['category_id' => '2', 'category_name' => 'Drama'],
    ]);
});
