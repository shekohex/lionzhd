<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Requests;

use App\Http\Integrations\Aria2\Responses\JsonRpcBatchResponse;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

final class JsonRpcBatchRequest extends Request implements HasBody
{
    use HasJsonBody;

    /**
     * The HTTP method of the request
     */
    final protected Method $method = Method::POST;

    /**
     * The response class
     */
    protected ?string $response = JsonRpcBatchResponse::class;

    /**
     * @param  list<JsonRpcRequest>  $calls
     */
    public function __construct(
        protected readonly array $calls,
    ) {
        if (empty($this->calls)) {
            throw new InvalidArgumentException('At least one call is required.');
        }
    }

    /**
     * The endpoint for the request
     */
    final public function resolveEndpoint(): string
    {
        return '/jsonrpc';
    }

    /**
     * Cast the response to a DTO.
     */
    final public function createDtoFromResponse(Response $response): mixed
    {
        return $this->response ? new $this->response($response->getPsrResponse(), $response->getPendingRequest(), $response->getPsrRequest()) : null;
    }

    /**
     * Default body
     *
     * @return array<string, mixed>
     */
    final public function defaultBody(): array
    {
        return Arr::map($this->calls, fn (JsonRpcRequest $call) => $call->defaultBody());
    }
}
