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
use Illuminate\Database\Eloquent\Builder;
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
        $asOf = $this->resolveAsOf($request);
        $asOfId = $this->resolveAsOfId($request);

        if ($categoryId !== null && ! $this->isValidSeriesCategory($categoryId)) {
            return to_route('series')->with('warning', 'Category not found. Showing all categories.');
        }

        $seriesQuery = Series::query()
            ->withExists(['watchlists as in_watchlist' => function ($query) use ($user): void {
                $query->where('user_id', $user->id);
            }])
            ->when($categoryId !== null, function (Builder $query) use ($categoryId): void {
                if ($categoryId === Category::UNCATEGORIZED_SERIES_PROVIDER_ID) {
                    $query->where(function (Builder $uncategorizedQuery) use ($categoryId): void {
                        $uncategorizedQuery
                            ->whereNull('category_id')
                            ->orWhere('category_id', '')
                            ->orWhere('category_id', $categoryId);
                    });

                    return;
                }

                $query->where('category_id', $categoryId);
            });

        if ($asOf === null || $asOfId === null) {
            $snapshot = (clone $seriesQuery)
                ->orderByDesc('last_modified')
                ->orderByDesc('series_id')
                ->first(['last_modified', 'series_id']);

            if ($snapshot !== null) {
                $asOf = $snapshot->getRawOriginal('last_modified');
                $asOfId = (int) $snapshot->series_id;
            }
        }

        if ($asOf !== null && $asOfId !== null) {
            $seriesQuery->where(static function (Builder $query) use ($asOf, $asOfId): void {
                $query
                    ->whereNull('last_modified')
                    ->orWhere(static function (Builder $nonNullQuery) use ($asOf, $asOfId): void {
                        $nonNullQuery
                            ->whereNotNull('last_modified')
                            ->where(static function (Builder $cutoffQuery) use ($asOf, $asOfId): void {
                                $cutoffQuery
                                    ->where('last_modified', '<', $asOf)
                                    ->orWhere(static function (Builder $sameTimestampQuery) use ($asOf, $asOfId): void {
                                        $sameTimestampQuery
                                            ->where('last_modified', '=', $asOf)
                                            ->where('series_id', '<=', $asOfId);
                                    });
                            });
                    });
            });
        }

        $series = $seriesQuery
            ->orderByDesc('last_modified')
            ->orderByDesc('series_id')
            ->paginate(20)
            ->appends(['as_of' => $asOf, 'as_of_id' => $asOfId])
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

    private function resolveAsOf(Request $request): ?string
    {
        $asOf = $request->query('as_of');

        if (! is_string($asOf)) {
            return null;
        }

        $trimmed = trim($asOf);

        return $trimmed === '' ? null : $trimmed;
    }

    private function resolveAsOfId(Request $request): ?int
    {
        $asOfId = $request->query('as_of_id');

        if (! is_scalar($asOfId)) {
            return null;
        }

        if (! is_numeric((string) $asOfId)) {
            return null;
        }

        $resolved = (int) $asOfId;

        return $resolved > 0 ? $resolved : null;
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
