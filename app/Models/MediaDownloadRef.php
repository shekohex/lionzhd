<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
        'media_id',
        'media_type',
        'downloadable_id',
        'episode',
    ];

    public static function fromVodStream(
        VodStream $vodStream,
        string $gid,
    ): self {
        return new self([
            'gid' => $gid,
            'media_id' => $vodStream->stream_id,
            'media_type' => VodStream::class,
            'downloadable_id' => $vodStream->stream_id,
        ]);
    }

    public static function fromSeriesAndEpisode(
        Series $series,
        string $gid,
        int $episode,
        int $episode_id,
    ): self {
        return new self([
            'gid' => $gid,
            'media_id' => $series->series_id,
            'media_type' => Series::class,
            'downloadable_id' => $episode_id,
            'episode' => $episode,
        ]);
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
}
