<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Responses;

/**
 * This is a response class for the addTorrent RPC method.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.addTorrent
 */
final class AddTorrentResponse extends JsonRpcResponse
{
    /**
     * Get the GID of the added torrent download.
     */
    public function getGid(): string
    {
        return $this->result();
    }
}
