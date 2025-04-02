<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AddToWatchlist;
use App\Actions\CreateXtreamcodesDownloadUrl;
use App\Actions\DownloadMedia;
use App\Actions\GetDownloadStatus;
use App\Actions\RemoveFromWatchlist;
use App\Data\MediaDownloadStatusData;
use App\Http\Integrations\LionzTv\Requests\GetVodInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\MediaDownloadRef;
use App\Models\User;
use App\Models\VodStream;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class VodStreamController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(#[CurrentUser] User $user): Response
    {
        $movies = VodStream::query()
            ->withExists(['watchlists as in_watchlist' => function ($query) use ($user): void {
                $query->where('user_id', $user->id);
            }])
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
        $vod = $client->send(new GetVodInfoRequest($model->stream_id));
        $inWatchlist = $user->inMyWatchlist($model->stream_id, VodStream::class);

        return Inertia::render('movies/show', [
            'info' => $vod->dtoOrFail(),
            'in_watchlist' => $inWatchlist,
        ]);
    }

    /**
     * Add an item to the user's watchlist.
     */
    public function addToWatchlist(#[CurrentUser] User $user, VodStream $model): RedirectResponse
    {
        $added = AddToWatchlist::run($user, $model->stream_id, VodStream::class);
        if (! $added) {
            return back()->withErrors('Failed to add movie to watchlist.');
        }

        return back()->with('success', 'Movie added to watchlist.');
    }

    /**
     * Remove an item from the user's watchlist.
     */
    public function removeFromWatchlist(#[CurrentUser] User $user, VodStream $model): RedirectResponse
    {
        $removed = RemoveFromWatchlist::run($user, $model->stream_id, VodStream::class);
        if (! $removed) {
            return back()->withErrors('Failed to remove movie from watchlist.');
        }

        return back()->with('success', 'Movie removed from watchlist.');
    }

    public function forgetCache(VodStream $model, XtreamCodesConnector $client): RedirectResponse
    {
        $req = (new GetVodInfoRequest($model->stream_id))->invalidateCache();

        $client->send($req);

        return back()->with('success', 'Cache cleared.');
    }

    /**
     * Trigger a download of the Video on demand stream.
     */
    public function download(#[CurrentUser] User $user, XtreamCodesConnector $client, VodStream $model): RedirectResponse
    {

        $vod = $client->send(new GetVodInfoRequest($model->stream_id));
        $dto = $vod->dtoOrFail();
        // Check if the user has already downloaded this stream and the download is still active
        $existingDownloads = MediaDownloadRef::query()
            ->where('media_id', $model->stream_id)
            ->where('media_type', VodStream::class)
            ->get();
        // Check if any of the existing downloads are still active
        $gids = $existingDownloads->pluck('gid');
        $firstActive = null;
        if ($gids->isNotEmpty()) {
            $activeDownloads = GetDownloadStatus::run($gids->toArray())->map(fn (array $response) => MediaDownloadStatusData::from($response));
            $firstActive = $activeDownloads->firstWhere(fn ($download) => $download->status->downloadedOrDownloading());
        }
        if ($firstActive) {
            return redirect()->route('downloads', [
                'downloadable_id' => $model->stream_id,
                'gid' => $firstActive->gid,
            ])->with('success', 'Download started.');
        }

        $url = CreateXtreamcodesDownloadUrl::run($dto);
        $gid = DownloadMedia::run($url);

        MediaDownloadRef::fromVodStream($model, $gid)->saveOrFail();

        return redirect()->route('downloads', [
            'downloadable_id' => $model->stream_id,
            'gid' => $gid,
        ])->with('success', 'Download started.');

    }
}
