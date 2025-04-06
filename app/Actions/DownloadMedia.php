<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\AsAction;
use App\Http\Integrations\Aria2\JsonRpcConnector;
use App\Http\Integrations\Aria2\Requests\AddUriRequest;
use App\Http\Integrations\Aria2\Responses\AddUriResponse;
use League\Uri\Uri;

/**
 * @method static string run(Uri $url, array $options = [])
 */
final readonly class DownloadMedia
{
    use AsAction;

    public function __construct(private readonly JsonRpcConnector $connector) {}

    /**
     * Execute the action.
     *
     * @param  array<string, mixed>  $options
     */
    public function __invoke(
        Uri $url,
        array $options = [],
    ): string {
        $req = new AddUriRequest(
            [$url],
            array_merge(
                [
                    'continue' => true,
                    'enable-http-pipelining' => true,
                    'allow-overwrite' => true,
                    'auto-file-renaming' => false,
                    'retry-wait' => 5,
                    'max-tries' => 10,
                ],
                $options,
            ),
        );

        /** @var AddUriResponse $response */
        $response = $this->connector->send($req)->dtoOrFail();

        return $response->getGid();

    }
}
