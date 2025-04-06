<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Responses;

/**
 * This is a response class for the addMetalink RPC method.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.addMetalink
 */
final class AddMetalinkResponse extends JsonRpcResponse
{
    /**
     * Get the GID of the added metalink download.
     */
    public function getGid(): string
    {
        return $this->result();
    }
}
