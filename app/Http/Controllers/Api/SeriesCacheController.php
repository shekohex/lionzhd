<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\InvalidateSeriesCache;
use App\Http\Controllers\Controller;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Http\Resources\Api\ApiActionResource;
use App\Models\Series;
use Illuminate\Support\Facades\Gate;

final class SeriesCacheController extends Controller
{
    public function destroy(XtreamCodesConnector $client, Series $series): ApiActionResource
    {
        Gate::authorize('admin');

        InvalidateSeriesCache::run($client, $series);

        return new ApiActionResource([
            'id' => "series-cache:{$series->series_id}",
            'type' => 'cache-invalidations',
            'attributes' => [
                'status' => 'invalidated',
            ],
        ]);
    }
}
