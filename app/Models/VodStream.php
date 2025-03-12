<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Scout\Searchable;

/**
 * @mixin IdeHelperVodStream
 */
final class VodStream extends Model
{
    use Searchable;

    /**
     * Indicates if the model's ID is auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The table associated with the model.
     */
    protected $table = 'vod_streams';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'num';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'num',
        'name',
        'stream_type',
        'stream_id',
        'stream_icon',
        'rating',
        'rating_5based',
        'added',
        'is_adult',
        'category_id',
        'container_extension',
        'custom_sid',
        'direct_source',
    ];

    /**
     * @return array<string,string>
     */
    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
        ];
    }

    /**
     * Get all watchlist entries for this VOD.
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'num' => 'integer',
            'stream_id' => 'integer',
            'rating_5based' => 'decimal:1',
            'is_adult' => 'boolean',
            'added' => 'immutable_datetime',
        ];
    }
}
