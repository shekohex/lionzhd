<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Responses;

/**
 * This is a response class for the pause RPC method.
 * Pauses a specific download. This method returns OK for success.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.pause
 */
final class PauseResponse extends JsonRpcResponse
{
    /**
     * Get the result of the pause operation.
     */
    public function getResult(): string
    {
        return (string) ($this->result() ?? 'OK');
    }
}
