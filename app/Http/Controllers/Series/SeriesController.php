<?php

declare(strict_types=1);

namespace App\Http\Controllers\Series;

use App\Actions\BuildCategorySidebarItems;
use App\Data\CategoryBrowseFiltersData;
use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Http\Integrations\LionzTv\Requests\GetSeriesInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\Category;
use App\Models\Series;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SeriesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, #[CurrentUser] User $user): Response|RedirectResponse
    {
        $categoryId = $this->resolveCategoryId($request);

        if ($categoryId !== null && ! $this->isValidSeriesCategory($categoryId)) {
            return to_route('series')->with('warning', 'Category not found. Showing all categories.');
        }

        $series = Series::query()
            ->withExists(['watchlists as in_watchlist' => function ($query) use ($user): void {
                $query->where('user_id', $user->id);
            }])
            ->when($categoryId !== null, function ($query) use ($categoryId): void {
                if ($categoryId === Category::UNCATEGORIZED_SERIES_PROVIDER_ID) {
                    $query->where(function ($uncategorizedQuery) use ($categoryId): void {
                        $uncategorizedQuery
                            ->whereNull('category_id')
                            ->orWhere('category_id', '')
                            ->orWhere('category_id', $categoryId);
                    });

                    return;
                }

                $query->where('category_id', $categoryId);
            })
            ->orderByDesc('last_modified')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('series/index', [
            'series' => fn () => $series,
            'filters' => fn () => new CategoryBrowseFiltersData(category: $categoryId),
            'categories' => fn () => BuildCategorySidebarItems::run(MediaType::Series, $categoryId),
        ]);
    }

    private function resolveCategoryId(Request $request): ?string
    {
        $requestedCategoryId = trim((string) $request->query('category', ''));

        return $requestedCategoryId === '' ? null : $requestedCategoryId;
    }

    private function isValidSeriesCategory(string $categoryId): bool
    {
        return Category::query()
            ->where('in_series', true)
            ->where('provider_id', $categoryId)
            ->exists();
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
