<?php

declare(strict_types=1);

namespace App\Http\Controllers\VodStream;

use App\Data\CategoryData;
use App\Http\Controllers\Controller;
use App\Http\Integrations\LionzTv\Requests\GetVodInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\Category;
use App\Models\User;
use App\Models\VodStream;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class VodStreamController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, #[CurrentUser] User $user): Response
    {
        $query = VodStream::query()
            ->withExists(['watchlists as in_watchlist' => function ($query) use ($user): void {
                $query->where('user_id', $user->id);
            }]);

        if ($request->has('category')) {
            $query->where('category_id', $request->input('category'));
        }

        $movies = $query->orderByDesc('added')
            ->paginate(20)
            ->withQueryString();

        $categories = Category::where('type', 'movie')
            ->orderBy('category_name')
            ->get();

        return Inertia::render('movies/index', [
            'movies' => $movies,
            'categories' => CategoryData::collect($categories),
            'filters' => $request->only(['category']),
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
}
