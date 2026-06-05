<?php

declare(strict_types=1);

namespace App\OpenApi;

use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;
use Dedoc\Scramble\Support\Generator\SecurityScheme;

final class BearerAuthSecurityStrategy extends MiddlewareAuthSecurityStrategy
{
    /**
     * @param  list<string>  $middleware
     */
    public function __construct(array $middleware = ['auth:sanctum'])
    {
        parent::__construct(
            middleware: $middleware,
            scheme: SecurityScheme::http('bearer')->as('bearerAuth'),
        );
    }
}
