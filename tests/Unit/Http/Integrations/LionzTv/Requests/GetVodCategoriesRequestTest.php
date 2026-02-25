<?php

declare(strict_types=1);

use App\Http\Integrations\LionzTv\Requests\GetVodCategoriesRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\XtreamCodesConfig;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('returns empty array when vod categories payload is not a list', function (): void {
    $request = new GetVodCategoriesRequest;
    $connector = (new XtreamCodesConnector(new XtreamCodesConfig([
        'host' => 'http://host.local',
        'port' => 80,
        'username' => 'user',
        'password' => 'secret',
    ])))->withMockClient(new MockClient([
        GetVodCategoriesRequest::class => MockResponse::make([
            'category_id' => '1',
            'category_name' => 'Action',
        ], 200),
    ]));

    $response = $connector->send($request);

    expect($request->createDtoFromResponse($response))->toBe([]);
});

it('filters non-array items from vod categories payload', function (): void {
    $payload = [
        ['category_id' => '1', 'category_name' => 'Action'],
        'bad-item',
        100,
        ['category_id' => '2', 'category_name' => 'Drama'],
        null,
    ];

    $request = new GetVodCategoriesRequest;
    $connector = (new XtreamCodesConnector(new XtreamCodesConfig([
        'host' => 'http://host.local',
        'port' => 80,
        'username' => 'user',
        'password' => 'secret',
    ])))->withMockClient(new MockClient([
        GetVodCategoriesRequest::class => MockResponse::make($payload, 200),
    ]));

    $response = $connector->send($request);

    expect($request->createDtoFromResponse($response))->toBe([
        ['category_id' => '1', 'category_name' => 'Action'],
        ['category_id' => '2', 'category_name' => 'Drama'],
    ]);
});
