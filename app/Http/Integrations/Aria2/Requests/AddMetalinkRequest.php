<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Requests;

use App\Http\Integrations\Aria2\Responses\AddMetalinkResponse;

/**
 * This is a request class for the addMetalink RPC method.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.addMetalink
 */
final class AddMetalinkRequest extends JsonRpcRequest
{
    /**
     * The response class
     */
    protected ?string $response = AddMetalinkResponse::class;

    /**
     * AddMetalinkRequest constructor.
     *
     * @param  string  $metalink  Base64 encoded metalink file data
     * @param  array<string, mixed>  $options  Optional array of options for the download
     */
    public function __construct(
        string $metalink,
        array $options = []
    ) {
        parent::__construct('addMetalink', [$metalink, $options]);
    }

    /**
     * Get the metalink data.
     */
    public function getMetalink(): string
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
