<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Responses;

/**
 * This is a response class for the remove RPC method.
 * This method removes a specific download.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.remove
 */
final class RemoveResponse extends JsonRpcResponse
{
    /**
     * Get the GID of the removed download.
     */
    public function getGid(): string
    {
        return (string) ($this->result() ?? '');
    }
}
