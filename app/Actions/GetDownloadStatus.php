<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\AsAction;
use App\Http\Integrations\Aria2\JsonRpcConnector;
use App\Http\Integrations\Aria2\Requests\JsonRpcBatchRequest;
use App\Http\Integrations\Aria2\Requests\TellStatusRequest;
use App\Http\Integrations\Aria2\Responses\JsonRpcBatchResponse;
use Illuminate\Support\Collection;

/**
 * @method static Collection<int, array<string, mixed>> run(array $gids, array $keys = [])
 */
final readonly class GetDownloadStatus
{
    use AsAction;

    public function __construct(private readonly JsonRpcConnector $connector) {}

    /**
     * Execute the action.
     *
     * @param  list<string>  $gids
     * @param  list<string>  $keys
     * @return Collection<int, array<string, mixed>>
     */
    public function __invoke(
        array $gids,
        array $keys = [],
    ): Collection {
        $calls = [];
        foreach ($gids as $gid) {
            $calls[] = new TellStatusRequest($gid, $keys);
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
