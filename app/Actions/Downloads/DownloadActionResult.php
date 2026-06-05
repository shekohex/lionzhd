<?php

declare(strict_types=1);

namespace App\Actions\Downloads;

use App\Http\Integrations\Aria2\JsonRpcException;

final readonly class DownloadActionResult
{
    private function __construct(
        public bool $ok,
        public bool $removed = false,
        public ?string $error = null,
        public int $status = 422,
        public ?JsonRpcException $unavailable = null,
    ) {}

    public static function succeeded(): self
    {
        return new self(true);
    }

    public static function removed(): self
    {
        return new self(true, removed: true);
    }

    public static function failed(string $error): self
    {
        return new self(false, error: $error);
    }

    public static function conflict(string $error): self
    {
        return new self(false, error: $error, status: 409);
    }

    public static function unavailable(JsonRpcException $exception): self
    {
        return new self(false, error: $exception->getMessage(), status: 503, unavailable: $exception);
    }
}
