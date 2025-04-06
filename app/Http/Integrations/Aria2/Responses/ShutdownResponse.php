<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Responses;

/**
 * This is a response class for the shutdown RPC method.
 * This method shutdowns aria2.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.shutdown
 */
final class ShutdownResponse extends JsonRpcResponse
{
    /**
     * Get the result of the shutdown operation.
     */
    public function getResult(): string
    {
        return (string) ($this->result() ?? 'OK');
    }
}
