<?php

declare(strict_types=1);

namespace App\Models\AutoEpisodes;

use App\Enums\AutoEpisodes\SeriesMonitorRunStatus;
use App\Enums\AutoEpisodes\SeriesMonitorRunTrigger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperSeriesMonitorRun
 */
final class SeriesMonitorRun extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'monitor_id',
        'trigger',
        'window_start_at',
        'window_end_at',
        'status',
        'queued_count',
        'duplicate_count',
        'deferred_count',
        'error_count',
        'error_message',
        'started_at',
        'finished_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'trigger' => SeriesMonitorRunTrigger::class,
        'window_start_at' => 'immutable_datetime',
        'window_end_at' => 'immutable_datetime',
        'status' => SeriesMonitorRunStatus::class,
        'queued_count' => 'integer',
        'duplicate_count' => 'integer',
        'deferred_count' => 'integer',
        'error_count' => 'integer',
        'started_at' => 'immutable_datetime',
        'finished_at' => 'immutable_datetime',
    ];

    /**
     * @return BelongsTo<SeriesMonitor,$this>
     */
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(SeriesMonitor::class, 'monitor_id');
    }

    /**
     * @return HasMany<SeriesMonitorEvent,$this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(SeriesMonitorEvent::class, 'run_id');
    }
}
