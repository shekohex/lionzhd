<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Requests;

use App\Http\Integrations\Aria2\Responses\TellActiveResponse;

/**
 * This is a request class for the tellActive RPC method.
 * This method returns a list of active downloads.
 * keys is an array of strings. If specified, the response only includes the keys specified in the keys.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.tellActive
 */
final class TellActiveRequest extends JsonRpcRequest
{
    /**
     * The response class
     */
    protected ?string $response = TellActiveResponse::class;

    /**
     * TellActiveRequest constructor.
     *
     * @param  array<string>  $keys  Array of keys to return. If empty, returns all keys.
     */
    public function __construct(array $keys = [])
    {
        parent::__construct('tellActive', $keys !== [] ? [$keys] : []);
    }

    /**
     * Get the keys.
     *
     * @return array<string>
     */
    public function getKeys(): array
    {
        return $this->params[0] ?? [];
    }
}
