<?php

declare(strict_types=1);

namespace App\Models\AutoEpisodes;

use App\Models\Series;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperSeriesMonitor
 */
final class SeriesMonitor extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'series_id',
        'watchlist_id',
        'enabled',
        'timezone',
        'schedule_type',
        'schedule_daily_time',
        'schedule_weekly_days',
        'schedule_weekly_time',
        'monitored_seasons',
        'per_run_cap',
        'next_run_at',
        'last_attempt_at',
        'last_attempt_status',
        'last_successful_check_at',
        'run_now_available_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'enabled' => 'boolean',
        'schedule_weekly_days' => 'array',
        'monitored_seasons' => 'array',
        'per_run_cap' => 'integer',
        'next_run_at' => 'immutable_datetime',
        'last_attempt_at' => 'immutable_datetime',
        'last_successful_check_at' => 'immutable_datetime',
        'run_now_available_at' => 'immutable_datetime',
    ];

    /**
     * @return BelongsTo<User,$this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Series,$this>
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class, 'series_id', 'series_id');
    }

    /**
     * @return HasMany<SeriesMonitorEpisode,$this>
     */
    public function episodes(): HasMany
    {
        return $this->hasMany(SeriesMonitorEpisode::class, 'monitor_id');
    }

    /**
     * @return HasMany<SeriesMonitorRun,$this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(SeriesMonitorRun::class, 'monitor_id');
    }

    /**
     * @return HasMany<SeriesMonitorEvent,$this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(SeriesMonitorEvent::class, 'monitor_id');
    }
}
