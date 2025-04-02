<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Responses;

use Illuminate\Support\Collection;
use Saloon\Http\Response;

final class JsonRpcBatchResponse extends Response
{
    /**
     * Get the result of the responses.
     *
     * @return Collection<int, mixed>
     */
    final public function results(): Collection
    {
        return collect($this->json());
    }
}
