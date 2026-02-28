<?php

declare(strict_types=1);

namespace App\Models\AutoEpisodes;

use App\Models\MediaDownloadRef;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperSeriesMonitorEpisode
 */
final class SeriesMonitorEpisode extends Model
{
    public const string STATE_PENDING = 'pending';

    public const string STATE_FAILED = 'failed';

    public const string STATE_SKIPPED = 'skipped';

    public const string STATE_QUEUED = 'queued';

    public const string STATE_DOWNLOADED = 'downloaded';

    public const string STATE_CANCELED = 'canceled';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'monitor_id',
        'episode_id',
        'season',
        'episode_num',
        'state',
        'first_seen_at',
        'last_seen_at',
        'last_queued_at',
        'last_download_ref_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'season' => 'integer',
        'episode_num' => 'integer',
        'first_seen_at' => 'immutable_datetime',
        'last_seen_at' => 'immutable_datetime',
        'last_queued_at' => 'immutable_datetime',
        'last_download_ref_id' => 'integer',
    ];

    /**
     * @return BelongsTo<SeriesMonitor,$this>
     */
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(SeriesMonitor::class, 'monitor_id');
    }

    /**
     * @return BelongsTo<MediaDownloadRef,$this>
     */
    public function downloadRef(): BelongsTo
    {
        return $this->belongsTo(MediaDownloadRef::class, 'last_download_ref_id');
    }
}
