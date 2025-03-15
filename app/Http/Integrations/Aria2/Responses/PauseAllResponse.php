<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Responses;

/**
 * This is a response class for the pauseAll RPC method.
 * This method is equal to calling pause() for every active/waiting download.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.pauseAll
 */
final class PauseAllResponse extends JsonRpcResponse
{
    /**
     * Get the result of the pause operation.
     */
    public function getResult(): string
    {
        return (string) ($this->result() ?? 'OK');
    }
}
