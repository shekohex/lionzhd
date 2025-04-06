<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Requests;

use App\Http\Integrations\Aria2\Responses\GetGlobalOptionsResponse;

/**
 * This is a request class for the getGlobalOption RPC method.
 * Returns global options.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.getGlobalOption
 */
final class GetGlobalOptionsRequest extends JsonRpcRequest
{
    /**
     * The response class
     */
    protected ?string $response = GetGlobalOptionsResponse::class;

    public function __construct()
    {
        parent::__construct('getGlobalOption');
    }
}
