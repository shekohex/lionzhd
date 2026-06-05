<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\AsAction;
use App\Http\Integrations\LionzTv\Requests\GetSeriesInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\Series;

/**
 * @method static bool run(XtreamCodesConnector $client, Series $series)
 */
final class InvalidateSeriesCache
{
    use AsAction;

    public function __invoke(XtreamCodesConnector $client, Series $series): bool
    {
        $client->send((new GetSeriesInfoRequest($series->series_id))->invalidateCache());

        return true;
    }
}
