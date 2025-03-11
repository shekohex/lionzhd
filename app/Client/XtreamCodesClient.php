<?php

declare(strict_types=1);

namespace App\Client;

use App\Enums\XtreamCodesAction;
use App\Exceptions\UnauthorizedAccessException;
use App\Http\Responses\XtreamCodes\SeriesInformation;
use App\Http\Responses\XtreamCodes\VodInformation;
use App\Models\HttpClientConfig;
use App\Models\XtreamCodesConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;

/**
 * XtreamCodes Client
 */
final readonly class XtreamCodesClient
{
    public function __construct(
        private readonly XtreamCodesConfig $xtreamCodeConfig,
        private readonly HttpClientConfig $httpClientConfig,
    ) {}

    /**
     * Authenticate with Xtream Codes API
     *
     * @return mixed The JSON Response
     *
     * @throws UnauthorizedAccessException|ConnectionException
     */
    public function authenticate(): mixed
    {
        $client = $this->httpClientConfig->createClient()->baseUrl($this->xtreamCodeConfig->baseUrl());
        $response = $client->get('/player_api.php', $this->xtreamCodeConfig->credentials());
        if ($response->successful()) {
            return $response->json();
        }

        throw new UnauthorizedAccessException($response->reason());
    }

    /**
     * Fetch Series from Xtream Codes API
     *
     * @return Collection The JSON response from the API as a collection
     *
     * @throws ConnectionException
     */
    public function series(): Collection
    {
        return $this->do(XtreamCodesAction::GetSeries);
    }

    /**
     * Fetch Vod Streams from Xtream Codes API
     *
     * @return Collection The JSON response from the API as a collection
     *
     * @throws ConnectionException
     */
    public function vodStreams(): Collection
    {
        return $this->do(XtreamCodesAction::GetVodStreams);
    }

    /**
     * Fetch Series Info from Xtream Codes API
     *
     * @param  int  $seriesId  The ID of the series
     *
     * @throws ConnectionException
     */
    public function seriesInfo(int $seriesId): SeriesInformation
    {
        $json = $this->do(XtreamCodesAction::GetSeriesInfo, ['series_id' => $seriesId]);

        return SeriesInformation::fromJson($seriesId, $json->all());
    }

    /**
     * Fetch Vod Info from Xtream Codes API
     *
     * @param  int  $vodId  The ID of the Vod
     *
     * @throws ConnectionException
     */
    public function vodInfo(int $vodId): VodInformation
    {
        $json = $this->do(XtreamCodesAction::GetVodInfo, ['vod_id' => $vodId]);

        return VodInformation::fromJson($vodId, $json->all());
    }

    /**
     * Perform an action on the Xtream Codes API
     *
     * @param  XtreamCodesAction  $action  The action to perform
     * @param  array  $params  Additional parameters for the action
     * @return Collection The JSON response from the API as a collection
     *
     * @throws ConnectionException
     */
    private function do(XtreamCodesAction $action, array $params = []): Collection
    {
        $client = $this->httpClientConfig->createClient()->baseUrl($this->xtreamCodeConfig->baseUrl());
        $response = $client->get(
            '/player_api.php',
            array_merge(
                $this->xtreamCodeConfig->credentialsWithAction($action),
                $params
            )
        );

        return $response->collect();
    }
}
