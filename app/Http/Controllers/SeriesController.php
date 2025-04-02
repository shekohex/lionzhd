<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AddToWatchlist;
use App\Actions\CreateXtreamcodesDownloadUrl;
use App\Actions\DownloadMedia;
use App\Actions\RemoveFromWatchlist;
use App\Http\Integrations\LionzTv\Requests\GetSeriesInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\MediaDownloadRef;
use App\Models\Series;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class SeriesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(#[CurrentUser] User $user): Response
    {
        $series = Series::query()
            ->withExists(['watchlists as in_watchlist' => function ($query) use ($user): void {
                $query->where('user_id', $user->id);
            }])
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
        $inWatchlist = $user->inMyWatchlist($model->series_id, Series::class);

        return Inertia::render('series/show', [
            'info' => $series,
            'in_watchlist' => $inWatchlist,
        ]);
    }

    /**
     * Add an item to the user's watchlist.
     */
    public function addToWatchlist(#[CurrentUser] User $user, Series $model): RedirectResponse
    {
        $added = AddToWatchlist::run($user, $model->series_id, Series::class);
        if (! $added) {
            return back()->withErrors('Failed to add series to watchlist.');
        }

        return back()->with('success', 'Series added to watchlist.');
    }

    public function forgetCache(Series $model, XtreamCodesConnector $client): RedirectResponse
    {
        $req = (new GetSeriesInfoRequest($model->series_id))->invalidateCache();
        $client->send($req);

        return back()->with('success', 'Cache cleared for the series.');

    }

    /**
     * Remove an item from the user's watchlist.
     */
    public function removeFromWatchlist(#[CurrentUser] User $user, Series $model): RedirectResponse
    {
        $removed = RemoveFromWatchlist::run($user, $model->series_id, Series::class);
        if (! $removed) {
            return back()->withErrors('Failed to remove series from watchlist.');
        }

        return back()->with('success', 'Series removed from watchlist.');
    }

    /**
     * Trigger a download of the series.
     */
    public function download(#[CurrentUser] User $user, XtreamCodesConnector $client, Series $model, int $season, int $episode): RedirectResponse
    {

        $series = $client->send(new GetSeriesInfoRequest($model->series_id));
        $dto = $series->dtoOrFail();
        $selectedEpisode = $dto->seasonsWithEpisodes[$season][$episode] ?? null;
        if ($selectedEpisode === null) {
            return back()->withErrors('Episode not found.');
        }

        $url = CreateXtreamcodesDownloadUrl::run($selectedEpisode);
        $gid = DownloadMedia::run($url);

        MediaDownloadRef::fromSeriesAndEpisode($model, $gid, $episode, (int) $selectedEpisode->id)->saveOrFail();

        return redirect()->route('downloads', [
            'downloadable_id' => $selectedEpisode->id,
            'series_id' => $model->series_id,
            'gid' => $gid,
        ])->with('success', 'Download started.');
    }
}
