<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Responses;

/**
 * This is a response class for the getSessionInfo RPC method.
 * Returns session information.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.getSessionInfo
 */
final class GetSessionInfoResponse extends JsonRpcResponse
{
    /**
     * Get the session information.
     *
     * Returns following keys:
     * - sessionId: Session ID
     * - numStoppedTotal: The number of stopped downloads in the current session
     * - numActive: The number of active downloads
     * - numWaiting: The number of waiting downloads
     * - numStopped: The number of stopped downloads
     *
     * @return array{
     *  sessionId: string,
     *  numStoppedTotal: int,
     *  numActive: int,
     *  numWaiting: int,
     *  numStopped: int
     * }
     */
    public function getSessionInfo(): array
    {
        return $this->result() ?? [];
    }
}
