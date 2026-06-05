<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\AsAction;
use App\Http\Integrations\LionzTv\Requests\GetVodInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\VodStream;

/**
 * @method static bool run(XtreamCodesConnector $client, VodStream $movie)
 */
final class InvalidateVodStreamCache
{
    use AsAction;

    public function __invoke(XtreamCodesConnector $client, VodStream $movie): bool
    {
        $client->send((new GetVodInfoRequest($movie->stream_id))->invalidateCache());

        return true;
    }
}
