<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Integrations\LionzTv\Requests\GetSeriesInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\Series;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
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
    public function show(#[CurrentUser] User $user, XtreamCodesConnector $client, Series $model): Response
    {
        $series = $client->send(new GetSeriesInfoRequest($model->series_id))->dtoOrFail();
        $inWatchlist = $user->inMyWatchlist($model->num, Series::class);

        return Inertia::render('series/show', [
            'series' => $series,
            'inWatchlist' => $inWatchlist,
        ]);
    }
}
