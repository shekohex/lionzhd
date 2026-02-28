<?php

declare(strict_types=1);

namespace App\Models\AutoEpisodes;

use App\Enums\AutoEpisodes\SeriesMonitorEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperSeriesMonitorEvent
 */
final class SeriesMonitorEvent extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'run_id',
        'monitor_id',
        'episode_id',
        'season',
        'episode_num',
        'type',
        'reason',
        'meta',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'season' => 'integer',
        'episode_num' => 'integer',
        'type' => SeriesMonitorEventType::class,
        'meta' => 'array',
    ];

    /**
     * @return BelongsTo<SeriesMonitor,$this>
     */
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(SeriesMonitor::class, 'monitor_id');
    }

    /**
     * @return BelongsTo<SeriesMonitorRun,$this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(SeriesMonitorRun::class, 'run_id');
    }
}
