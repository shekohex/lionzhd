<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Responses;

/**
 * This is a response class for the saveSession RPC method.
 * This method saves the current session to a file specified by the --save-session option.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.saveSession
 */
final class SaveSessionResponse extends JsonRpcResponse
{
    /**
     * Get the result of the save operation.
     */
    public function getResult(): string
    {
        return (string) ($this->result() ?? 'OK');
    }
}
