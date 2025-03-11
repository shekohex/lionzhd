<?php

declare(strict_types=1);

namespace App\Rpc;

use GuzzleHttp\Promise\PromiseInterface;

final readonly class Response
{
    public function __construct(
        private string|int $id,
        private mixed $result,
        private mixed $error,
        private PromiseInterface|\Illuminate\Http\Client\Response $response
    ) {}

    public function response(): PromiseInterface|\Illuminate\Http\Response
    {
        return $this->response;
    }

    public function result(): mixed
    {
        return $this->result;
    }

    public function error(): mixed
    {
        return $this->error;
    }

    public function id(): string|int
    {
        return $this->id;
    }

    public function hasError(): bool
    {
        return ! empty($this->error) || $this->response->failed();
    }
}
