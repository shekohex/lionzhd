<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\Series;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

final class SeriesResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->series()->getKey();
    }

    public function toType(Request $request): string
    {
        return 'series';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        $series = $this->series();

        return [
            'num' => (int) $series->num,
            'name' => (string) $series->name,
            'series_id' => (int) $series->series_id,
            'cover' => $series->cover,
            'plot' => $series->plot,
            'cast' => $series->cast,
            'director' => $series->director,
            'genre' => $series->genre,
            'backdrop_path' => $series->backdrop_path,
            'releaseDate' => $series->releaseDate,
            'last_modified' => $series->last_modified,
            'category_id' => $series->category_id,
            'rating' => $series->rating,
            'rating_5based' => $series->rating_5based,
            'created_at' => $series->created_at,
            'updated_at' => $series->updated_at,
            ...$this->episodesAttributes(),
        ];
    }

    /**
     * @return array{seasons: array<int, string>, episodes: array<string, mixed>}|array{}
     */
    private function episodesAttributes(): array
    {
        $attributes = $this->series()->getAttributes();

        if (! array_key_exists('api_series_seasons', $attributes) || ! array_key_exists('api_series_episodes', $attributes)) {
            return [];
        }

        $seasons = $attributes['api_series_seasons'];
        $episodes = $attributes['api_series_episodes'];

        if (! is_array($seasons) || ! is_array($episodes)) {
            return [];
        }

        /** @var array<int, string> $seasons */
        /** @var array<string, mixed> $episodes */
        return [
            'seasons' => $seasons,
            'episodes' => $episodes,
        ];
    }

    private function series(): Series
    {
        /** @var Series $series */
        $series = $this->resource;

        return $series;
    }
}
