<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MediaType;
use App\Enums\SearchSortby;
use App\Models\Series;
use App\Models\VodStream;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

final readonly class MediaSearchService
{
    /**
     * Format a lightweight movie results for the API response
     *
     * @param  Paginator<VodStream>  $movies
     * @return array<string,mixed>
     */
    public static function lightweightMovieResults(Paginator $movies): array
    {
        return $movies->through(fn ($movie) => [
            'id' => $movie->num,
            'name' => $movie->name,
            'type' => MediaType::Movie,
            'poster' => $movie->stream_icon,
        ])->toArray();
    }

    /**
     * Search for movies based on given query and filters
     */
    public function searchMovies(
        string $query,
        ?int $limit = null,
        ?SearchSortby $sortBy = null,
        ?int $page = null
    ): LengthAwarePaginator {
        if ($query === '' || $query === '0') {
            return new Paginator([], 0, $limit ?? 10);
        }

        $movieQuery = VodStream::search($query);

        // Apply sorting
        if ($sortBy instanceof SearchSortby) {
            match ($sortBy) {
                SearchSortby::Popular => $movieQuery->orderBy('rating_5based', 'desc'),
                SearchSortby::Latest => $movieQuery->orderBy('added', 'desc'),
                SearchSortby::Rating => $movieQuery->orderBy('rating_5based', 'desc'),
            };
        }

        return $movieQuery->paginate(
            $limit ?? 10,
            page: $page
        )->withQueryString();
    }

    /**
     * Search for TV series based on given query and filters
     */
    public function searchSeries(
        string $query,
        ?int $limit = null,
        ?SearchSortby $sortBy = null,
        ?int $page = null
    ): LengthAwarePaginator {
        if ($query === '' || $query === '0') {
            return new Paginator([], 0, $limit ?? 10);
        }

        $seriesQuery = Series::search($query);

        // Apply sorting
        if ($sortBy instanceof SearchSortby) {
            match ($sortBy) {
                SearchSortby::Popular => $seriesQuery->orderBy('rating', 'desc'),
                SearchSortby::Latest => $seriesQuery->orderBy('last_modified', 'desc'),
                SearchSortby::Rating => $seriesQuery->orderBy('rating', 'desc'),
            };
        }

        return $seriesQuery->paginate(
            $limit ?? 10,
            page: $page
        )->withQueryString();
    }

    /**
     * Format a lightweight series results for the API response
     *
     * @param  Paginator<Series>  $series
     * @return array<string,mixed>
     */
    public function lightweightSeriesResults(Paginator $series): array
    {
        return $series->through(fn ($series) => [
            'id' => $series->num,
            'name' => $series->name,
            'type' => 'series',
            'poster' => $series->cover,
        ])->toArray();
    }
}
