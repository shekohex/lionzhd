<?php

declare(strict_types=1);

namespace App\Http\Controllers\VodStream;

use App\Actions\CreateSignedPlaybackLink;
use App\Http\Controllers\Controller;
use App\Http\Integrations\LionzTv\Requests\GetVodInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\VodStream;
use Illuminate\Http\JsonResponse;

final class VodStreamPlaybackController extends Controller
{
    public function show(
        XtreamCodesConnector $client,
        CreateSignedPlaybackLink $createSignedPlaybackLink,
        VodStream $model,
    ): JsonResponse {
        $response = $client->send(new GetVodInfoRequest($model->stream_id));

        if ($response->status() === 404) {
            abort(404);
        }

        return response()->json([
            'url' => $createSignedPlaybackLink($response->dtoOrFail()),
        ]);
    }
}
