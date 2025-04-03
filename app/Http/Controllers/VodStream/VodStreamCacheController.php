<?php

declare(strict_types=1);

namespace App\Http\Controllers\VodStream;

use App\Http\Controllers\Controller;
use App\Http\Integrations\LionzTv\Requests\GetVodInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\VodStream;
use Illuminate\Http\RedirectResponse;

final class VodStreamCacheController extends Controller
{
    public function destroy(VodStream $model, XtreamCodesConnector $client): RedirectResponse
    {
        $req = new GetVodInfoRequest($model->stream_id);
        $req = $req->invalidateCache();

        $client->send($req);

        return back();
    }
}
