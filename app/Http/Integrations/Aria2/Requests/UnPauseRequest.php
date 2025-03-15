<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Requests;

use App\Http\Integrations\Aria2\Responses\UnPauseResponse;

/**
 * This is a request class for the unpause RPC method.
 * This method resumes the download denoted by gid.
 * This method returns OK for success.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.unpause
 */
final class UnPauseRequest extends JsonRpcRequest
{
    /**
     * The response class
     */
    protected ?string $response = UnPauseResponse::class;

    /**
     * UnPauseRequest constructor.
     *
     * @param  string  $gid  The GID of the download to resume
     */
    public function __construct(string $gid)
    {
        parent::__construct('unpause', [$gid]);
    }

    /**
     * Get the GID.
     */
    public function getGid(): string
    {
        return $this->params[0];
    }
}
