<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Responses;

/**
 * This is a response class for the forceShutdown RPC method.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.forceShutdown
 */
final class ForceShutdownResponse extends JsonRpcResponse
{
    /**
     * Get the result of the shutdown operation.
     */
    public function getResult(): string
    {
        return (string) ($this->result() ?? 'OK');
    }
}
