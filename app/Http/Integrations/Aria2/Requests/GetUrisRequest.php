<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Requests;

use App\Http\Integrations\Aria2\Responses\GetUrisResponse;

/**
 * This is a request class for the getUris RPC method.
 * Returns the list of URIs used in the download.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.getUris
 */
final class GetUrisRequest extends JsonRpcRequest
{
    /**
     * The response class
     */
    protected ?string $response = GetUrisResponse::class;

    /**
     * GetUrisRequest constructor.
     *
     * @param  string  $gid  The GID of the download
     */
    public function __construct(string $gid)
    {
        parent::__construct('getUris', [$gid]);
    }

    /**
     * Get the GID.
     */
    public function getGid(): string
    {
        return $this->params[0];
    }
}
