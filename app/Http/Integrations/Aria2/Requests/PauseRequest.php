<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Requests;

use App\Http\Integrations\Aria2\Responses\PauseResponse;

/**
 * This is a request class for the pause RPC method.
 * This method pauses the download denoted by gid.
 * The status of paused download becomes "paused".
 * This method returns OK for success.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.pause
 */
final class PauseRequest extends JsonRpcRequest
{
    /**
     * The response class
     */
    protected ?string $response = PauseResponse::class;

    /**
     * PauseRequest constructor.
     *
     * @param  string  $gid  The GID of the download to pause
     */
    public function __construct(string $gid)
    {
        parent::__construct('pause', [$gid]);
    }

    /**
     * Get the GID.
     */
    public function getGid(): string
    {
        return $this->params[0];
    }
}
