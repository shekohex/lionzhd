<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Requests;

use App\Http\Integrations\Aria2\Responses\TellStatusResponse;

/**
 * This is a request class for the tellStatus RPC method.
 * This method returns the download progress of a specific download.
 * keys is an array of strings. If specified, the response only includes the keys specified in the keys.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.tellStatus
 */
final class TellStatusRequest extends JsonRpcRequest
{
    /**
     * The response class
     */
    protected ?string $response = TellStatusResponse::class;

    /**
     * TellStatusRequest constructor.
     *
     * @param  string  $gid  The GID of the download
     * @param  array<string>  $keys  Array of keys to return. If empty, returns all keys
     */
    public function __construct(string $gid, array $keys = [])
    {
        parent::__construct('tellStatus', $keys !== [] ? [$gid, $keys] : [$gid]);
    }

    /**
     * Get the GID.
     */
    public function getGid(): string
    {
        return $this->params[0];
    }

    /**
     * Get the keys.
     *
     * @return array<string>
     */
    public function getKeys(): array
    {
        return $this->params[1] ?? [];
    }
}
