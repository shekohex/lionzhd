<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Requests;

use App\Http\Integrations\Aria2\Responses\ForceShutdownResponse;

/**
 * This is a request class for the forceShutdown RPC method.
 * Shutting down aria2. This method shuts down aria2. This method returns OK for success.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.forceShutdown
 */
final class ForceShutdownRequest extends JsonRpcRequest
{
    /**
     * The response class
     */
    protected ?string $response = ForceShutdownResponse::class;

    public function __construct()
    {
        parent::__construct('forceShutdown');
    }
}
