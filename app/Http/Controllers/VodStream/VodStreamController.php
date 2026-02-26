<?php

declare(strict_types=1);

namespace App\Http\Controllers\VodStream;

use App\Actions\BuildCategorySidebarItems;
use App\Data\CategoryBrowseFiltersData;
use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Http\Integrations\LionzTv\Requests\GetVodInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\Category;
use App\Models\User;
use App\Models\VodStream;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class VodStreamController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, #[CurrentUser] User $user): Response|RedirectResponse
    {
        $requestedCategoryId = trim((string) $request->query('category', ''));
        $categoryId = $requestedCategoryId === '' ? null : $requestedCategoryId;

        $allowedCategoryIds = Category::query()
            ->where('in_vod', true)
            ->pluck('provider_id')
            ->all();

        if ($categoryId !== null && ! in_array($categoryId, $allowedCategoryIds, true)) {
            return to_route('movies')->with('warning', 'Category not found. Showing all categories.');
        }

        $movies = VodStream::query()
            ->withExists(['watchlists as in_watchlist' => function ($query) use ($user): void {
                $query->where('user_id', $user->id);
            }])
            ->when($categoryId !== null, function (Builder $query) use ($categoryId): void {
                if ($categoryId === Category::UNCATEGORIZED_VOD_PROVIDER_ID) {
                    $query->where(static function (Builder $innerQuery) use ($categoryId): void {
                        $innerQuery
                            ->whereNull('category_id')
                            ->orWhere('category_id', '')
                            ->orWhere('category_id', $categoryId);
                    });

                    return;
                }

                $query->where('category_id', $categoryId);
            })
            ->orderByDesc('added')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('movies/index', [
            'movies' => fn () => $movies,
            'filters' => fn (): CategoryBrowseFiltersData => new CategoryBrowseFiltersData(category: $categoryId),
            'categories' => fn () => BuildCategorySidebarItems::run(MediaType::Movie, $categoryId),
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
