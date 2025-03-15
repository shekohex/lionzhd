<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Responses;

/**
 * This is a response class for the tellStatus RPC method.
 * This method returns a detailed download status.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.tellStatus
 */
final class TellStatusResponse extends JsonRpcResponse
{
    /**
     * Get the download status.
     *
     * @return array{
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
     *  }>,
     *  errorCode?: string,
     *  errorMessage?: string,
     *  dir?: string,
     *  bittorrent?: array{
     *      announceList?: array<array<string>>,
     *      comment?: string,
     *      creationDate?: int,
     *      mode?: string,
     *      info?: array{
     *          name?: string
     *      }
     *  }
     * }
     */
    public function getStatus(): array
    {
        return $this->result() ?? [];
    }
}
