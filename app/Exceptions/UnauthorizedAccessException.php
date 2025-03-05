<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Support\Facades\Log;
use Throwable;

class UnauthorizedAccessException extends Exception implements ShouldntReport
{
    public function __construct(string $body, int $code = 401, ?Throwable $previous = null)
    {
        parent::__construct('Authentication failed. Response: '.$body, $code, $previous);
    }

    public function report(): void
    {
        Log::error($this->getMessage());
    }
}
