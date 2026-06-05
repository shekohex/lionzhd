<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\AsAction;
use App\Http\Integrations\LionzTv\Responses\VodInformation;
use App\Models\MediaDownloadRef;
use App\Models\User;
use App\Models\VodStream;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * @method static array{gid: string, existing: bool, preparing: bool} run(User $user, VodStream $movie, VodInformation $dto)
 */
final class QueueVodStreamDownload
{
    use AsAction;

    private const int DOWNLOAD_LOCK_TTL_SECONDS = 15;

    private const int DOWNLOAD_LOCK_WAIT_SECONDS = 3;

    private const int SAVE_ATTEMPTS = 5;

    /**
     * @return array{gid: string, existing: bool, preparing: bool}
     */
    public function __invoke(User $user, VodStream $movie, VodInformation $dto): array
    {
        try {
            return Cache::lock(
                MediaDownloadRef::lockKeyForVodStream($user, $movie),
                self::DOWNLOAD_LOCK_TTL_SECONDS,
            )->block(self::DOWNLOAD_LOCK_WAIT_SECONDS, fn (): array => $this->queue($user, $movie, $dto));
        } catch (LockTimeoutException) {
            $activeDownload = GetActiveDownloads::run($movie, user: $user);

            if ($activeDownload === null) {
                return ['gid' => '', 'existing' => false, 'preparing' => true];
            }

            return ['gid' => $activeDownload->gid, 'existing' => true, 'preparing' => false];
        }
    }

    /**
     * @return array{gid: string, existing: bool, preparing: bool}
     */
    private function queue(User $user, VodStream $movie, VodInformation $dto): array
    {
        $activeDownload = GetActiveDownloads::run($movie, user: $user);

        if ($activeDownload !== null) {
            return ['gid' => $activeDownload->gid, 'existing' => true, 'preparing' => false];
        }

        $gid = DownloadMedia::run(CreateXtreamcodesDownloadUrl::run($dto), ['out' => CreateDownloadOut::run($dto)]);
        $this->persistDownloadRef(MediaDownloadRef::fromVodStream($gid, $movie, $user));

        return ['gid' => $gid, 'existing' => false, 'preparing' => false];
    }

    private function persistDownloadRef(MediaDownloadRef $downloadRef): void
    {
        $saved = DB::transaction(static fn (): bool => $downloadRef->save(), attempts: self::SAVE_ATTEMPTS);

        if (! $saved) {
            throw new RuntimeException('Failed to save download reference.');
        }
    }
}
