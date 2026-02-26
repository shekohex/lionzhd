<?php

declare(strict_types=1);

namespace App\Models;

use App\Http\Integrations\LionzTv\Responses\Episode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @mixin IdeHelperMediaDownloadRef
 */
final class MediaDownloadRef extends Model
{
    /**
     * the attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'gid',
        'user_id',
        'media_id',
        'media_type',
        'downloadable_id',
        'season',
        'episode',
        'desired_paused',
        'canceled_at',
        'cancel_delete_partial',
        'last_error_code',
        'last_error_message',
        'retry_attempt',
        'retry_next_at',
        'download_files',
    ];

    /**
     * the attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'desired_paused' => 'boolean',
        'canceled_at' => 'immutable_datetime',
        'cancel_delete_partial' => 'boolean',
        'last_error_code' => 'integer',
        'retry_attempt' => 'integer',
        'retry_next_at' => 'immutable_datetime',
        'download_files' => 'array',
    ];

    public static function fromVodStream(
        string $gid,
        VodStream $vodStream,
        User|int|null $owner = null,
    ): self {
        return new self([
            'gid' => $gid,
            'user_id' => self::ownerId($owner),
            'media_id' => $vodStream->stream_id,
            'media_type' => VodStream::class,
            'downloadable_id' => $vodStream->stream_id,
        ]);
    }

    public static function fromSeriesAndEpisode(
        string $gid,
        Series $series,
        Episode $episode,
        User|int|null $owner = null,
    ): self {
        return new self([
            'gid' => $gid,
            'user_id' => self::ownerId($owner),
            'media_id' => $series->series_id,
            'media_type' => Series::class,
            'downloadable_id' => $episode->id,
            'season' => $episode->season,
            'episode' => $episode->episodeNum,
        ]);
    }

    public function isVodStream(): bool
    {
        return $this->media_type === VodStream::class;
    }

    public function isSeriesWithEpisode(): bool
    {
        return $this->media_type === Series::class && $this->episode !== null;
    }

    /**
     * Get the media model (either VOD or Series).
     *
     * @return MorphTo<Model,$this>
     */
    public function media(): MorphTo
    {
        return $this->morphTo();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    private static function ownerId(User|int|null $owner): ?int
    {
        if ($owner instanceof User) {
            return $owner->id;
        }

        return $owner;
    }
}
