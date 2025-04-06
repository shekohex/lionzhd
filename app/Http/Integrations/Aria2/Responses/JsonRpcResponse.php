<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Responses;

use Saloon\Http\Response;

abstract class JsonRpcResponse extends Response
{
    /**
     * Get the result of the response.
     */
    final public function result(): mixed
    {
        return $this->json('result');
    }

    /**
     * Get the error of the response.
     */
    final public function error(): mixed
    {
        return $this->json('error');
    }

    /**
     * Get the ID of the response.
     */
    final public function id(): string|int|null
    {
        return $this->json('id');
    }

    /**
     * Check if the response has an error.
     */
    final public function hasError(): bool
    {
        return $this->json('error') !== null;
    }

    /**
     * Check if the response has an error with a specific code.
     */
    final public function hasErrorCode(int $code): bool
    {
        return $this->errorCode() === $code;
    }

    /**
     * Get the error code.
     */
    final public function errorCode(): mixed
    {
        return $this->json('error.code');
    }

    /**
     * Get the error message.
     */
    final public function errorMessage(): string
    {
        return $this->json('error.message', '');
    }

    /**
     * Check if the response has a result.
     */
    final public function hasResult(): bool
    {
        return $this->json('result') !== null;
    }
}
