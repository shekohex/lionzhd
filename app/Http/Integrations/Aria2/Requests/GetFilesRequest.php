<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Requests;

use App\Http\Integrations\Aria2\Responses\GetFilesResponse;

/**
 * This is a request class for the getFiles RPC method.
 * Returns the file list of a download.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.getFiles
 */
final class GetFilesRequest extends JsonRpcRequest
{
    /**
     * The response class
     */
    protected ?string $response = GetFilesResponse::class;

    /**
     * GetFilesRequest constructor.
     *
     * @param  string  $gid  The GID of the download
     */
    public function __construct(string $gid)
    {
        parent::__construct('getFiles', [$gid]);
    }

    /**
     * Get the GID.
     */
    public function getGid(): string
    {
        return $this->params[0];
    }
}
