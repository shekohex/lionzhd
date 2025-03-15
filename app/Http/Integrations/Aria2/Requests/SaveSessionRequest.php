<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Requests;

use App\Http\Integrations\Aria2\Responses\SaveSessionResponse;

/**
 * This is a request class for the saveSession RPC method.
 * This method saves the current session to a file specified by the --save-session option.
 * This method returns OK if it succeeds.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.saveSession
 */
final class SaveSessionRequest extends JsonRpcRequest
{
    /**
     * The response class
     */
    protected ?string $response = SaveSessionResponse::class;

    public function __construct()
    {
        parent::__construct('saveSession');
    }
}
