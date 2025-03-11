<?php

declare(strict_types=1);

namespace App\Exceptions\Aria2;

use Exception;

final class AuthenticationException extends Exception
{
    public function __construct()
    {
        parent::__construct('Invalid or missing authentication token', 401);
    }
}
