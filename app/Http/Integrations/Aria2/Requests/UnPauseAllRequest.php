<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Requests;

use App\Http\Integrations\Aria2\Responses\UnPauseAllResponse;

/**
 * This is a request class for the unpauseAll RPC method.
 * This method resumes all paused downloads.
 * This method returns OK for success.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.unpauseAll
 */
final class UnPauseAllRequest extends JsonRpcRequest
{
    /**
     * The response class
     */
    protected ?string $response = UnPauseAllResponse::class;

    public function __construct()
    {
        parent::__construct('unpauseAll');
    }
}
