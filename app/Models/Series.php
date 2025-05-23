<?php

declare(strict_types=1);

namespace App\Models;

use App\Data\SeriesData;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Scout\Searchable;
use Spatie\LaravelData\WithData;

/**
 * @mixin IdeHelperSeries
 */
final class Series extends Model
{
    use Searchable;
    use WithData;

    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    protected $dataClass = SeriesData::class;

    /**
     * Indicates if all mass assignment is enabled.
     *
     * @var bool
     */
    protected static $unguarded = true;

    /**
     * The table associated with the model.
     */
    protected $table = 'series';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'series_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'series_id',
        'cover',
        'plot',
        'cast',
        'director',
        'genre',
        'releaseDate',
        'last_modified',
        'rating',
        'rating_5based',
        'backdrop_path',
        'youtube_trailer',
        'episode_run_time',
        'category_id',
    ];

    /**
     * @return array<string,mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
            'plot' => $this->plot,
            'cast' => $this->cast,
            'director' => $this->director,
            'genre' => $this->genre,
        ];
    }

    /**
     * Get all watchlist entries for this series.
     *
     * @return MorphMany<Watchlist,$this>
     */
    public function watchlists(): MorphMany
    {
        return $this->morphMany(Watchlist::class, 'watchable');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'series_id' => 'integer',
            'rating_5based' => 'decimal:1',
            'backdrop_path' => AsArrayObject::class,
            'last_modified' => 'immutable_datetime',
        ];
    }
}
