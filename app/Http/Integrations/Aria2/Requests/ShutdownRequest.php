<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Requests;

use App\Http\Integrations\Aria2\Responses\ShutdownResponse;

/**
 * This is a request class for the shutdown RPC method.
 * This method shutdowns aria2. This method behaves like :func:'aria2.shutdown` without performing
 * any actions which take time, such as contacting BitTorrent trackers to
 * unregister downloads first.
 * This method returns OK for success.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.shutdown
 */
final class ShutdownRequest extends JsonRpcRequest
{
    /**
     * The response class
     */
    protected ?string $response = ShutdownResponse::class;

    public function __construct()
    {
        parent::__construct('shutdown');
    }
}
