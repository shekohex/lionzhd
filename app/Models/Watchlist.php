<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @mixin IdeHelperWatchlist
 */
final class Watchlist extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'watchable_id',
        'watchable_type',
    ];

    /**
     * Get the user that owns the watchlist item.
     *
     * @return BelongsTo<User,$this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the watchable model (either VOD or Series).
     *
     * @return MorphTo<Model,$this>
     */
    public function watchable(): MorphTo
    {
        return $this->morphTo();
    }
}
