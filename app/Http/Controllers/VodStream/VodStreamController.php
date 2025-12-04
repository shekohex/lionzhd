<?php

declare(strict_types=1);

namespace App\Http\Controllers\VodStream;

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
    public function index(#[CurrentUser] User $user, Request $request): Response
    {
        $categoryId = $request->query('category');
        $currentCategory = null;

        if ($categoryId) {
            $currentCategory = Category::where('id', $categoryId)
                ->where('type', 'movie')
                ->first();
        }

        $movies = VodStream::query()
            ->when($categoryId, function ($query, $catId) {
                $query->where('category_id', $catId);
            })
            ->withExists(['watchlists as in_watchlist' => function ($query) use ($user): void {
                $query->where('user_id', $user->id);
            }])
            ->orderByDesc('added')
            ->paginate(20)
            ->withQueryString();

        $categories = Category::where('type', 'movie')->orderBy('name')->get();

        return Inertia::render('movies/index', [
            'movies' => $movies,
            'categories' => $categories,
            'currentCategory' => $currentCategory,
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
