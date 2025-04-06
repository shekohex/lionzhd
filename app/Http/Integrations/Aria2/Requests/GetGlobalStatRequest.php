<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Requests;

use App\Http\Integrations\Aria2\Responses\GetGlobalStatResponse;

/**
 * This is a request class for the getGlobalStat RPC method.
 * Returns global statistics such as overall download and upload speeds.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.getGlobalStat
 */
final class GetGlobalStatRequest extends JsonRpcRequest
{
    /**
     * The response class
     */
    protected ?string $response = GetGlobalStatResponse::class;

    public function __construct()
    {
        parent::__construct('getGlobalStat');
    }
}
