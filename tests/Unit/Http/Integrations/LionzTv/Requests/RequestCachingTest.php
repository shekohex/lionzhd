<?php

declare(strict_types=1);

use App\Http\Integrations\LionzTv\Requests\GetSeriesRequest;
use App\Http\Integrations\LionzTv\Requests\GetVodStreamsRequest;
use Saloon\CachePlugin\Contracts\Cacheable;

it('does not cache media list requests', function (): void {
    expect(in_array(Cacheable::class, class_implements(GetSeriesRequest::class), true))->toBeFalse();
    expect(in_array(Cacheable::class, class_implements(GetVodStreamsRequest::class), true))->toBeFalse();
});
