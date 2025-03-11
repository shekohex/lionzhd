<?php

declare(strict_types=1);

namespace App\Exceptions\Aria2;

use Exception;

final class DownloadException extends Exception
{
    public function __construct(string $gid, string $message)
    {
        parent::__construct("Download {$gid}: {$message}", 400);
    }
}
