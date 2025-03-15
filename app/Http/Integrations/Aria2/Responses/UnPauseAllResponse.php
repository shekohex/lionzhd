<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Responses;

/**
 * This is a response class for the unpauseAll RPC method.
 * This method resumes all paused downloads.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.unpauseAll
 */
final class UnPauseAllResponse extends JsonRpcResponse
{
    /**
     * Get the result of the unpause operation.
     */
    public function getResult(): string
    {
        return (string) ($this->result() ?? 'OK');
    }
}
