<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\MediaDownloadStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class MediaDownloadStatusData extends Data
{
    public function __construct(
        /**
         * The GID of the download
         */
        public string $gid = '',
        /**
         * The current status of the media download
         */
        public MediaDownloadStatus $status = MediaDownloadStatus::UNKNOWN,
        /**
         * The total length of the media (in bytes)
         */
        public int $totalLength = 0,
        /**
         * The completed length of the media (in bytes)
         */
        public int $completedLength = 0,
        /**
         * The download speed (in bytes per second)
         */
        public int $downloadSpeed = 0,
        /**
         * The error code (if any)
         */
        public int $errorCode = 0,
        /**
         * The error message (if any)
         */
        public ?string $errorMessage = null,
        /**
         * Directory to save files.
         */
        public string $dir = '',
        /**
         * Returns the list of files. The elements of this list are the same structs used in `aria2.getFiles()` method.
         *
         * @var list<DownloadedFileData>
         */ public array $files = []
    ) {}
}
