<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Client\XtreamCodesClient;
use App\Models\User;
use App\Models\VodStream;
use Illuminate\Support\Facades\Auth;
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
    public function show(XtreamCodesClient $client, VodStream $model): Response
    {
        $vod = $client->vodInfo($model->stream_id);
        /** @var User $user */
        $user = Auth::user();
        $inWatchlist = $user->inMyWatchlist($model->num, VodStream::class);

        return Inertia::render('movies/show', [
            'movie' => $vod,
            'inWatchlist' => $inWatchlist,
        ]);
    }
}
