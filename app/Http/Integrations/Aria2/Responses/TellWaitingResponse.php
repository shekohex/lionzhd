<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Responses;

/**
 * This is a response class for the tellWaiting RPC method.
 * This method returns a list of waiting downloads.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.tellWaiting
 */
final class TellWaitingResponse extends JsonRpcResponse
{
    /**
     * Get the list of waiting downloads.
     *
     * The response is an array of the same structs as returned by aria2.tellStatus() method.
     *
     * @return array<array{
     *  gid: string,
     *  status: string,
     *  totalLength: string,
     *  completedLength: string,
     *  uploadLength: string,
     *  downloadSpeed: string,
     *  uploadSpeed: string,
     *  connections: string,
     *  numSeeders: string,
     *  seeder: bool,
     *  files: array<array{
     *      index: string,
     *      path: string,
     *      length: string,
     *      completedLength: string,
     *      selected: bool,
     *      uris: array<array{uri: string, status: string}>
     *  }>
     * }>
     */
    public function getWaitingDownloads(): array
    {
        return $this->result() ?? [];
    }
}
