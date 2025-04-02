<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2;

use Exception;
use Saloon\Http\Response;
use Throwable;

final class JsonRpcException extends Exception
{
    /**
     * The Saloon HTTP response.
     */
    protected ?Response $rawResponse;

    /**
     * The Aria2 error code.
     */
    protected ?int $aria2ErrorCode;

    /**
     * Create a new JsonRpcException instance.
     *
     * @return void
     */
    public function __construct(Response $response, ?Throwable $previous = null)
    {
        $this->rawResponse = $response;
        $this->extractErrorData();

        parent::__construct(
            $this->extractErrorMessage(),
            $this->aria2ErrorCode ?? 0,
            $previous
        );
    }

    /**
     * Get the Saloon response.
     */
    public function getResponse(): ?Response
    {
        return $this->rawResponse;
    }

    /**
     * Get the Aria2 error code.
     */
    public function getAria2ErrorCode(): ?int
    {
        return $this->aria2ErrorCode;
    }

    /**
     * Extract error data from the response.
     */
    protected function extractErrorData(): void
    {
        $responseData = $this->rawResponse->json();

        if (isset($responseData['error'])) {
            $this->aria2ErrorCode = $responseData['error']['code'] ?? 0;
        }
    }

    /**
     * Extract the error message from the response.
     */
    protected function extractErrorMessage(): string
    {
        $responseData = $this->rawResponse->json();
        if (isset($responseData['error']['message'])) {
            return $responseData['error']['message'];
        }

        return 'Unknown Aria2 JSON-RPC error';
    }
}
