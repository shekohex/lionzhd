<?php

declare(strict_types=1);

namespace App\Models;

use App\Data\VodStreamData;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Scout\Searchable;
use Spatie\LaravelData\WithData;

/**
 * @mixin IdeHelperVodStream
 */
final class VodStream extends Model
{
    use Searchable;
    use WithData;

    /**
     * Indicates if the model's ID is auto-incrementing.
     */
    public $incrementing = false;

    protected $dataClass = VodStreamData::class;

    /**
     * The table associated with the model.
     */
    protected $table = 'vod_streams';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'stream_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
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
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $added = $this->getAttribute('added');

        return [
            'name' => $this->name,
            'category_id' => $this->category_id,
            'added' => $added instanceof DateTimeInterface ? $added->getTimestamp() : null,
            'rating' => $this->rating === null ? null : (float) $this->rating,
            'rating_5based' => (float) $this->rating_5based,
            'created_at' => $this->created_at?->getTimestamp(),
            'updated_at' => $this->updated_at?->getTimestamp(),
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
            'stream_id' => 'integer',
            'rating_5based' => 'decimal:1',
            'is_adult' => 'boolean',
            'added' => 'immutable_datetime',
        ];
    }
}
