<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Requests;

use App\Http\Integrations\Aria2\Responses\RemoveResponse;

/**
 * This is a request class for the remove RPC method.
 * This method removes the download denoted by gid. If the specified download is in progress,
 * it is stopped first. The status of removed download becomes "removed".
 * This method returns GID of removed download.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.remove
 */
final class RemoveRequest extends JsonRpcRequest
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
        parent::__construct('remove', [$gid]);
    }

    /**
     * Get the GID.
     */
    public function getGid(): string
    {
        return $this->params[0];
    }
}
