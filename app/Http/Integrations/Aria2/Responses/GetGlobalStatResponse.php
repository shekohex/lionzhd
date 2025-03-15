<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Responses;

/**
 * This is a response class for the getGlobalStat RPC method.
 * Returns global statistics such as overall download and upload speeds.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.getGlobalStat
 */
final class GetGlobalStatResponse extends JsonRpcResponse
{
    /**
     * Get the global statistics.
     *
     * Returns following keys:
     * - downloadSpeed: Overall download speed (byte/sec)
     * - uploadSpeed: Overall upload speed (byte/sec)
     * - numActive: The number of active downloads
     * - numWaiting: The number of waiting downloads
     * - numStopped: The number of stopped downloads
     * - numStoppedTotal: The number of stopped downloads in the current session
     *
     * @return array{
     *  downloadSpeed: int,
     *  uploadSpeed: int,
     *  numActive: int,
     *  numWaiting: int,
     *  numStopped: int,
     *  numStoppedTotal: int
     * }
     */
    public function getStats(): array
    {
        return $this->result() ?? [];
    }
}
