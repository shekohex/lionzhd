<?php

declare(strict_types=1);

namespace App\Http\Controllers\Series;

use App\Http\Controllers\Controller;
use App\Http\Integrations\LionzTv\Requests\GetSeriesInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\Series;
use Illuminate\Http\RedirectResponse;

final class SeriesCacheController extends Controller
{
    public function destroy(Series $model, XtreamCodesConnector $client): RedirectResponse
    {
        $req = new GetSeriesInfoRequest($model->series_id);
        $req = $req->invalidateCache();
        $client->send($req);

        return back();

    }
}
