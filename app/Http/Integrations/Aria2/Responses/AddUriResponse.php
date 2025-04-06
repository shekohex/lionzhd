<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Responses;

/**
 * This is a response class for the addUri RPC method.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.addUri
 */
final class AddUriResponse extends JsonRpcResponse
{
    /**
     * Get the GID of the added URI download.
     *
     * @return string GID of the added URI.
     */
    public function getGid(): string
    {
        return $this->result();
    }
}
