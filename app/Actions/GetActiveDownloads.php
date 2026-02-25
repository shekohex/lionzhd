<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\AsAction;
use App\Data\MediaDownloadStatusData;
use App\Enums\UserRole;
use App\Http\Integrations\LionzTv\Responses\Episode;
use App\Models\MediaDownloadRef;
use App\Models\Series;
use App\Models\User;
use App\Models\VodStream;
use Illuminate\Support\Collection;

/**
 * @method static ?MediaDownloadStatusData run(VodStream|Series $media, Episode|null $episode = null, User|null $user = null)
 */
final readonly class GetActiveDownloads
{
    use AsAction;

    /**
     * Execute the action.
     */
    public function __invoke(VodStream|Series $media, ?Episode $episode = null, ?User $user = null): ?MediaDownloadStatusData
    {

        $media_id = match ($media::class) {
            VodStream::class => $media->stream_id,
            Series::class => $media->series_id,
        };

        $downloadable_id = match ($media::class) {
            VodStream::class => $media->stream_id,
            Series::class => $episode->id,
        };

        $existingDownloads = MediaDownloadRef::query()
            ->where('media_id', $media_id)
            ->where('media_type', $media::class)
            ->where('downloadable_id', $downloadable_id)
            ->when($episode, fn ($query) => $query->where('episode', $episode->episodeNum))
            ->when($user?->role === UserRole::Member, fn ($query) => $query->where('user_id', $user->id))
            ->get();

        $gids = $existingDownloads->pluck('gid');

        if ($existingDownloads->isEmpty()) {
            return null;
        }

        /** @var Collection<int, MediaDownloadStatusData> */
        $activeDownloads = GetDownloadStatus::run($gids->toArray())->map(fn (array $response) => MediaDownloadStatusData::from($response));

        return $activeDownloads->firstWhere(fn ($download) => $download->status->downloadedOrDownloading());

    }
}
