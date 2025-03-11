<?php

declare(strict_types=1);

namespace App\Exceptions\Aria2;

use Exception;
use Throwable;

final class Aria2Exception extends Exception
{
    public function __construct(string $message, int $code = 401, ?Throwable $previous = null)
    {
        parent::__construct("Aria2 Error: {$message}", $code, $previous);
    }
}
