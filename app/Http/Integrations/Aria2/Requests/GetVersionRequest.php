<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Requests;

use App\Http\Integrations\Aria2\Responses\GetVersionResponse;

/**
 * This is a request class for the getVersion RPC method.
 * Returns version of the program and the list of enabled features.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.getVersion
 */
final class GetVersionRequest extends JsonRpcRequest
{
    /**
     * The response class
     */
    protected ?string $response = GetVersionResponse::class;

    public function __construct()
    {
        parent::__construct('getVersion');
    }
}
