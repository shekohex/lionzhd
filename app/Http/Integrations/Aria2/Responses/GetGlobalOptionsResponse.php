<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Responses;

/**
 * This is a response class for the getGlobalOption RPC method.
 * Returns global options such as the maximum number of connections and other settings.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.getGlobalOption
 */
final class GetGlobalOptionsResponse extends JsonRpcResponse
{
    /**
     * Get the global options.
     *
     * @return array{
     *  dir: string
     * }
     */
    public function getOptions(): array
    {
        return $this->result() ?? [];
    }
}
