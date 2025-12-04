<?php

declare(strict_types=1);

namespace App\Http\Controllers\Series;

use App\Data\CategoryData;
use App\Http\Controllers\Controller;
use App\Http\Integrations\LionzTv\Requests\GetSeriesInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\Category;
use App\Models\Series;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SeriesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, #[CurrentUser] User $user): Response
    {
        $query = Series::query()
            ->withExists(['watchlists as in_watchlist' => function ($query) use ($user): void {
                $query->where('user_id', $user->id);
            }]);

        if ($request->has('category')) {
            $query->where('category_id', $request->input('category'));
        }

        $series = $query->orderByDesc('last_modified')
            ->paginate(20)
            ->withQueryString();

        $categories = Category::where('type', 'series')
            ->orderBy('category_name')
            ->get();

        return Inertia::render('series/index', [
            'series' => $series,
            'categories' => CategoryData::collect($categories),
            'filters' => $request->only(['category']),
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
}
