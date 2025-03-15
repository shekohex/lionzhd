<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Integrations\LionzTv\Requests\GetVodInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\User;
use App\Models\VodStream;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final class VodStreamController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $movies = VodStream::query()
            ->orderByDesc('added')
            ->paginate(20);

        return Inertia::render('movies/index', [
            'movies' => $movies,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(#[CurrentUser] User $user, XtreamCodesConnector $client, VodStream $model): Response
    {
        $vod = $client->send(new GetVodInfoRequest($model->stream_id))->dtoOrFail();
        $inWatchlist = $user->inMyWatchlist($model->num, VodStream::class);

        return Inertia::render('movies/show', [
            'movie' => $vod,
            'inWatchlist' => $inWatchlist,
        ]);
    }
}
