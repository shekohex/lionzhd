<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Requests;

use App\Http\Integrations\Aria2\Responses\AddUriResponse;
use Illuminate\Support\Uri;

final class AddUriRequest extends JsonRpcRequest
{
    protected ?string $response = AddUriResponse::class;

    /**
     * AddUriRequest constructor.
     *
     * @param  array<Uri>  $uris
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        array $uris,
        array $options = []
    ) {
        parent::__construct('addUri', [$uris, $options]);
    }

    /**
     * Get the URIs.
     *
     * @return array<Uri>
     */
    public function getUris(): array
    {
        return $this->params[0];
    }

    /**
     * Get the options.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->params[1];
    }
}
