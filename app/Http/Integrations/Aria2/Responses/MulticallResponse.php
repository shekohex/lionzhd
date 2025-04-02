<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Responses;

/**
 * This is a response class for the multicall RPC method.
 * This method returns a list of results for the given method calls.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.multicall
 */
final class MulticallResponse extends JsonRpcResponse
{
    //
}
