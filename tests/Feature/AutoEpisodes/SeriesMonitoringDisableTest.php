<?php

declare(strict_types=1);

use App\Enums\UserSubtype;
use App\Models\Series;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps web monitoring disable idempotent and can still remove watchlist without monitor', function (): void {
    $user = User::factory()->memberInternal()->create(['subtype' => UserSubtype::Internal]);
    $series = seriesMonitoringDisableCreateSeries(701);
    $watchlist = $user->watchlists()->create(['watchable_type' => Series::class, 'watchable_id' => $series->series_id]);

    $this->actingAs($user)
        ->delete(route('series.monitoring.destroy', ['model' => $series->series_id]), ['remove_from_watchlist' => true])
        ->assertRedirect()
        ->assertSessionHas('success', 'Series monitoring disabled.');

    expect($watchlist->fresh())->toBeNull();
});

function seriesMonitoringDisableCreateSeries(int $seriesId): Series
{
    /** @var Series $series */
    $series = Series::withoutSyncingToSearch(static function () use ($seriesId): Series {
        $series = new Series;

        $series->forceFill([
            'series_id' => $seriesId,
            'num' => $seriesId,
            'name' => 'Disable Series',
            'cover' => 'https://example.test/cover.jpg',
            'plot' => 'Plot',
            'cast' => 'Cast',
            'director' => 'Director',
            'genre' => 'Drama',
            'releaseDate' => '2026-01-01',
            'last_modified' => now(),
            'rating' => 8.0,
            'rating_5based' => 4.0,
            'backdrop_path' => ['https://example.test/backdrop.jpg'],
            'category_id' => 'drama',
        ])->save();

        return $series;
    });

    return $series;
}
