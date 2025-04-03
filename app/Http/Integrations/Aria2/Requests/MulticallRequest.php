<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Requests;

use App\Http\Integrations\Aria2\Responses\MulticallResponse;
use InvalidArgumentException;

/**
 * This is a request class for the multicall RPC method.
 * Allows multiple RPC calls to be made in a single request.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#system.multicall
 */
final class MulticallRequest extends JsonRpcRequest
{
    /**
     * The response class
     */
    protected ?string $response = MulticallResponse::class;

    /**
     * MulticallRequest constructor.
     *
     * @param  array{methodName:string,params:array}  $requests  An array of requests to be made (without the aria2 prefix)
     */
    public function __construct(array $requests)
    {

        throw_if($requests === [], new InvalidArgumentException('At least one request is required.'));

        foreach ($requests as &$request) {
            throw_unless(isset($request['methodName'], $request['params']), new InvalidArgumentException('Each request must contain methodName and params.'));
            throw_unless(is_array($request['params']), new InvalidArgumentException('params must be an array.'));
            throw_unless(is_string($request['methodName']), new InvalidArgumentException('methodName must be a string.'));

            // Append the aria2 prefix to the method name if it doesn't already have it
            if (! str_starts_with($request['methodName'], 'aria2.')) {
                $request['methodName'] = 'aria2.'.$request['methodName'];
            }
        }

        parent::__construct('multicall', [$requests], systemCall: true);
    }
}
