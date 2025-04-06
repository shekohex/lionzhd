<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Requests;

use App\Http\Integrations\Aria2\Responses\RemoveResponse;

/**
 * This is a request class for the force remove RPC method.
 * This method removes the download denoted by gid.
 * This method behaves just like aria2.remove() except that this method removes the download without performing any actions which take time
 * such as contacting BitTorrent trackers to unregister the download first.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.forceRemove
 */
final class ForceRemoveRequest extends JsonRpcRequest
{
    /**
     * The response class
     */
    protected ?string $response = RemoveResponse::class;

    /**
     * RemoveRequest constructor.
     *
     * @param  string  $gid  The GID of the download to remove
     */
    public function __construct(string $gid)
    {
        parent::__construct('forceRemove', [$gid]);
    }

    /**
     * Get the GID.
     */
    public function getGid(): string
    {
        return $this->params[0];
    }
}
