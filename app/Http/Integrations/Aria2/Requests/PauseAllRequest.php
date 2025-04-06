<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Requests;

use App\Http\Integrations\Aria2\Responses\PauseAllResponse;

/**
 * This is a request class for the pauseAll RPC method.
 * This method is equal to calling pause() for every active/waiting download.
 * This method returns OK for success.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.pauseAll
 */
final class PauseAllRequest extends JsonRpcRequest
{
    /**
     * The response class
     */
    protected ?string $response = PauseAllResponse::class;

    public function __construct()
    {
        parent::__construct('pauseAll');
    }
}
