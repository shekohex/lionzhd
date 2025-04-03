<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Requests;

use App\Http\Integrations\Aria2\Responses\RemoveDownloadResultResponse;

/**
 * This is a request class for the remove download result RPC method.
 * This method removes a completed/error/removed download denoted by gid from memory. This method returns OK for success.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.removeDownloadResult
 */
final class RemoveDownloadResultRequest extends JsonRpcRequest
{
    /**
     * The response class
     */
    protected ?string $response = RemoveDownloadResultResponse::class;

    /**
     * RemoveDownloadResultRequest constructor.
     *
     * @param  string  $gid  The GID of the download to remove
     */
    public function __construct(string $gid)
    {
        parent::__construct('removeDownloadResult', [$gid]);
    }

    /**
     * Get the GID.
     */
    public function getGid(): string
    {
        return $this->params[0];
    }
}
