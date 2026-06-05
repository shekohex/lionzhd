<?php

declare(strict_types=1);

namespace App\Http\Controllers\VodStream;

use App\Actions\InvalidateVodStreamCache;
use App\Http\Controllers\Controller;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\VodStream;
use Illuminate\Http\RedirectResponse;

final class VodStreamCacheController extends Controller
{
    public function destroy(VodStream $model, XtreamCodesConnector $client): RedirectResponse
    {
        InvalidateVodStreamCache::run($client, $model);

        return back();
    }
}
