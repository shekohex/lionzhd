<?php

declare(strict_types=1);

namespace App\Models;

use App\Http\Integrations\LionzTv\Responses\Episode;
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
        string $gid,
        VodStream $vodStream,
    ): self {
        return new self([
            'gid' => $gid,
            'media_id' => $vodStream->stream_id,
            'media_type' => VodStream::class,
            'downloadable_id' => $vodStream->stream_id,
        ]);
    }

    public static function fromSeriesAndEpisode(
        string $gid,
        Series $series,
        Episode $episode,
    ): self {
        return new self([
            'gid' => $gid,
            'media_id' => $series->series_id,
            'media_type' => Series::class,
            'downloadable_id' => $episode->id,
            'episode' => $episode->episodeNum,
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
