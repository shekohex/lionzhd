<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Client\XtreamCodesClient;
use App\Models\Series;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class SeriesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $series = Series::query()
            ->orderByDesc('last_modified')
            ->paginate(20);

        return Inertia::render('series/index', [
            'series' => $series,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(XtreamCodesClient $client, Series $model): Response
    {
        $series = $client->seriesInfo($model->series_id);
        /** @var User $user */
        $user = Auth::user();
        $inWatchlist = $user->inMyWatchlist($model->num, Series::class);

        return Inertia::render('series/show', [
            'series' => $series,
            'inWatchlist' => $inWatchlist,
        ]);
    }
}
