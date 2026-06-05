<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

final class SeriesEpisodesSelectionRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'episodes' => ['required', 'array', 'min:1'],
            'episodes.*.season' => ['required', 'integer', 'min:1'],
            'episodes.*.episode' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return list<array{season: int, episode: int}>
     */
    public function selectedEpisodes(): array
    {
        return collect($this->input('episodes', []))
            ->map(static fn (array $episode): array => [
                'season' => (int) $episode['season'],
                'episode' => (int) $episode['episode'],
            ])
            ->values()
            ->all();
    }
}
