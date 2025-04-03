<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\AsAction;
use App\Http\Integrations\Aria2\JsonRpcConnector;
use App\Http\Integrations\Aria2\Requests\AddUriRequest;
use App\Http\Integrations\Aria2\Requests\JsonRpcBatchRequest;
use App\Http\Integrations\Aria2\Responses\JsonRpcBatchResponse;
use Closure;
use Illuminate\Support\Collection;
use League\Uri\Uri;

/**
 * @method static Collection<int, mixed> run(Uri[] $urls, null|Closure(int): array<string, mixed> $optionsFn = null)
 */
final readonly class BatchDownloadMedia
{
    use AsAction;

    public function __construct(private readonly JsonRpcConnector $connector) {}

    /**
     * Execute the action.
     *
     * @param  Uri[]  $urls
     * @param  ?Closure(int): array<string, mixed>  $optionsFn
     * @return Collection<int, mixed>
     */
    public function __invoke(
        array $urls,
        ?Closure $optionsFn = null,
    ): Collection {
        $calls = [];
        foreach ($urls as $key => $url) {
            $options = $optionsFn instanceof Closure ? $optionsFn($key) : [];
            $calls[] = new AddUriRequest(
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
        }

        $req = new JsonRpcBatchRequest($calls);

        /** @var JsonRpcBatchResponse $response */
        $response = $this->connector->send($req)->dtoOrFail();

        return $response->results()->map(function (array $response) {
            if (isset($response['error'])) {
                return [
                    'error' => $response['error']['message'] ?? 'Unknown error',
                ];
            }

            return $response['result'];

        });

    }
}
