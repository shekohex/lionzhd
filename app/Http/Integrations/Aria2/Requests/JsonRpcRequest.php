<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Requests;

use App\Http\Integrations\Aria2\Responses\JsonRpcResponse;
use Illuminate\Support\Str;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

abstract class JsonRpcRequest extends Request implements HasBody
{
    use HasJsonBody;

    /**
     * The HTTP method of the request
     */
    final protected Method $method = Method::POST;

    /**
     * The response class
     */
    protected ?string $response = JsonRpcResponse::class;

    /**
     * @param  list<mixed>  $params
     */
    protected function __construct(
        protected readonly string $call,
        protected array $params = [],
        protected bool $systemCall = false,
        protected ?string $id = null,
        protected readonly string $version = '2.0'
    ) {
        $this->id ??= Str::uuid()->toString();
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
        return [
            'jsonrpc' => $this->version,
            'method' => $this->systemCall ? "system.{$this->call}" : "aria2.{$this->call}",
            'params' => $this->params ?? [],
            'id' => $this->id,
        ];
    }
}
