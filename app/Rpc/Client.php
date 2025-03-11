<?php

declare(strict_types=1);

namespace App\Rpc;

use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class Client
{
    private array $batch = [];

    private bool $isBatch = false;

    /**
     * @var Factory
     */
    private $http;

    public function __construct(PendingRequest $http)
    {
        $this->http = $http->acceptJson()->asJson();
    }

    /**
     * Execute a batch of requests.
     *
     * @param  Closure(Client): void  $callback
     */
    public function batch(Closure $callback): Collection
    {
        $this->isBatch = true;

        $callback($this);

        $response = $this->http->post('', $this->batch);

        $this->isBatch = false;
        $this->batch = [];

        return $response->collect()->mapWithKeys(function ($content) use ($response) {
            $rpcResponse = $this->prepareResponse(collect($content), $response);

            return [$rpcResponse->id() => $rpcResponse];
        });
    }

    /**
     * Execute a single request.
     *
     * @return $this->isBatch is true ? $this : Response
     */
    public function execute(string $method, ?array $params = null, ?string $id = null): self|Response
    {
        return $this->request($method, $params, $id ?? Str::uuid()->toString());
    }

    /**
     * @param  array<int,mixed>  $params
     */
    public function notify(string $method, array $params = []): self
    {
        return $this->request($method, $params);
    }

    /**
     * @param  PromiseInterface|Response  $response
     * @param  Collection<array-key,mixed>  $data
     */
    private function prepareResponse(Collection $data, Response $response): Response
    {
        return new Response(
            $data->get('id'),
            $data->get('result'),
            $data->get('error'),
            $response
        );
    }

    /**
     * @return $this->isBatch is true ? $this : Response
     */
    private function request(string $method, ?array $params, string|int|null $id = null): self|Response
    {
        $data = collect([
            'jsonrpc' => '2.0',
            'id' => $id,
            'params' => $params,
            'method' => $method,
        ])->filter()->toArray();

        if ($this->isBatch) {
            $this->batch[] = $data;

            return $this;
        }

        return $this->call($data);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function call(array $data): Response
    {
        $response = $this->http->post('', $data);

        return $this->prepareResponse($response->collect(), $response);
    }
}
