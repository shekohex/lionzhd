<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Requests;

use App\Http\Integrations\Aria2\Responses\TellWaitingResponse;

/**
 * This is a request class for the tellWaiting RPC method.
 * This method returns a list of waiting downloads.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.tellWaiting
 */
final class TellWaitingRequest extends JsonRpcRequest
{
    /**
     * The response class
     */
    protected ?string $response = TellWaitingResponse::class;

    /**
     * TellWaitingRequest constructor.
     *
     * @param  int  $offset  The offset from the latest download to get the download status
     * @param  int  $num  The number of downloads to return
     * @param  array<string>  $keys  Array of keys to return. If empty, returns all keys
     */
    public function __construct(int $offset, int $num, array $keys = [])
    {
        parent::__construct('tellWaiting', $keys !== [] ? [$offset, $num, $keys] : [$offset, $num]);
    }

    /**
     * Get the offset.
     */
    public function getOffset(): int
    {
        return $this->params[0];
    }

    /**
     * Get the number of downloads to return.
     */
    public function getNum(): int
    {
        return $this->params[1];
    }

    /**
     * Get the keys.
     *
     * @return array<string>
     */
    public function getKeys(): array
    {
        return $this->params[2] ?? [];
    }
}
