<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Responses;

/**
 * This is a response class for the getUris RPC method.
 * Returns the list of URIs used in the download.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.getUris
 */
final class GetUrisResponse extends JsonRpcResponse
{
    /**
     * Get the list of URIs.
     *
     * Each URI element contains following keys:
     * - uri: URI
     * - status: 'used' if the URI is in use, 'waiting' if the URI is still waiting in the queue
     *
     * @return array<array{uri: string, status: string}>
     */
    public function getUris(): array
    {
        return $this->result() ?? [];
    }
}
