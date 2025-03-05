<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Series extends Model
{
    use Searchable;

    /**
     * Indicates if the model's ID is auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The table associated with the model.
     */
    protected $table = 'series';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'num';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'num',
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
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'num' => 'integer',
        'series_id' => 'integer',
        'rating_5based' => 'decimal:1',
        'backdrop_path' => AsArrayObject::class,
        'releaseDate' => 'date',
    ];

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
}
