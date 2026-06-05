<?php

declare(strict_types=1);

namespace App\Http\Controllers\Series;

use App\Actions\InvalidateSeriesCache;
use App\Http\Controllers\Controller;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\Series;
use Illuminate\Http\RedirectResponse;

final class SeriesCacheController extends Controller
{
    public function destroy(Series $model, XtreamCodesConnector $client): RedirectResponse
    {
        InvalidateSeriesCache::run($client, $model);

        return back();
    }
}
