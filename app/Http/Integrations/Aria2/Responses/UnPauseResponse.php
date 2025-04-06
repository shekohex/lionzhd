<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Responses;

/**
 * This is a response class for the unpause RPC method.
 * This method resumes a paused download.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.unpause
 */
final class UnPauseResponse extends JsonRpcResponse
{
    /**
     * Get the result of the unpause operation.
     */
    public function getResult(): string
    {
        return (string) ($this->result() ?? 'OK');
    }
}
