<?php

declare(strict_types=1);

namespace App\Http\Controllers\VodStream;

use App\Actions\BuildPersonalizedCategorySidebar;
use App\Data\CategoryBrowseFiltersData;
use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Http\Integrations\LionzTv\Requests\GetVodInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\Category;
use App\Models\User;
use App\Models\UserCategoryPreference;
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
        $asOf = $this->resolveAsOf($request);
        $asOfId = $this->resolveAsOfId($request);

        $allowedCategoryIds = Category::query()
            ->where('in_vod', true)
            ->pluck('provider_id')
            ->all();

        if ($categoryId !== null && ! in_array($categoryId, $allowedCategoryIds, true)) {
            return to_route('movies')->with('warning', 'Category not found. Showing all categories.');
        }

        $ignoredCategoryIds = $this->resolveIgnoredMovieCategoryIds($user);

        $moviesQuery = VodStream::query()
            ->withExists(['watchlists as in_watchlist' => function ($query) use ($user): void {
                $query->where('user_id', $user->id);
            }])
            ->when($ignoredCategoryIds !== [], static function (Builder $query) use ($ignoredCategoryIds): void {
                $query->whereNotIn('category_id', $ignoredCategoryIds);
            })
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
            });

        if ($asOf === null || $asOfId === null) {
            $snapshot = (clone $moviesQuery)
                ->orderByDesc('added')
                ->orderByDesc('stream_id')
                ->first(['added', 'stream_id']);

            if ($snapshot !== null) {
                $asOf = $snapshot->getRawOriginal('added');
                $asOfId = (int) $snapshot->stream_id;
            }
        }

        if ($asOf !== null && $asOfId !== null) {
            $moviesQuery->where(static function (Builder $query) use ($asOf, $asOfId): void {
                $query
                    ->where('added', '<', $asOf)
                    ->orWhere(static function (Builder $sameTimestampQuery) use ($asOf, $asOfId): void {
                        $sameTimestampQuery
                            ->where('added', '=', $asOf)
                            ->where('stream_id', '<=', $asOfId);
                    });
            });
        }

        $movies = $moviesQuery
            ->orderByDesc('added')
            ->orderByDesc('stream_id')
            ->paginate(20)
            ->appends(['as_of' => $asOf, 'as_of_id' => $asOfId])
            ->withQueryString();

        return Inertia::render('movies/index', [
            'movies' => fn () => $movies,
            'filters' => fn (): CategoryBrowseFiltersData => new CategoryBrowseFiltersData(category: $categoryId, recovery: null),
            'categories' => fn () => BuildPersonalizedCategorySidebar::run($user, MediaType::Movie, $categoryId),
        ]);
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

    private function resolveIgnoredMovieCategoryIds(User $user): array
    {
        return UserCategoryPreference::query()
            ->where('user_id', $user->getKey())
            ->where('media_type', MediaType::Movie->value)
            ->where('is_ignored', true)
            ->pluck('category_provider_id')
            ->filter(static fn (mixed $value): bool => is_string($value) && $value !== '')
            ->values()
            ->all();
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
