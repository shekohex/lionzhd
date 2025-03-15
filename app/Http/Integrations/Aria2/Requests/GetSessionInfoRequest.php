<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Requests;

use App\Http\Integrations\Aria2\Responses\GetSessionInfoResponse;

/**
 * This is a request class for the getSessionInfo RPC method.
 * Returns session information.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.getSessionInfo
 */
final class GetSessionInfoRequest extends JsonRpcRequest
{
    /**
     * The response class
     */
    protected ?string $response = GetSessionInfoResponse::class;

    public function __construct()
    {
        parent::__construct('getSessionInfo');
    }
}
