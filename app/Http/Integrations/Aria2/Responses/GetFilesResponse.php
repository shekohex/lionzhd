<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Responses;

/**
 * This is a response class for the getFiles RPC method.
 * Returns the file list of a download.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.getFiles
 */
final class GetFilesResponse extends JsonRpcResponse
{
    /**
     * Get the list of files.
     *
     * Each file entry contains following keys:
     * - index: Index of the file, starting at 1
     * - path: Path of the file
     * - length: File size in bytes
     * - completedLength: Completed length of this file in bytes
     * - selected: true if this file is selected by --select-file option
     * - uris: Returns a list of URIs for this file
     *
     * @return array{
     *  index: int,
     *  path: string,
     *  length: int,
     *  completedLength: int,
     *  selected: bool,
     *  uris: array<array{uri: string, status: string}>
     * }[]
     */
    public function getFiles(): array
    {
        return $this->result() ?? [];
    }
}
