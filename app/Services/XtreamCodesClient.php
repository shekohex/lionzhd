<?php

namespace App\Services;

use App\Enums\XtreamCodesAction;
use App\Exceptions\UnauthorizedAccessException;
use App\Http\Responses\XtreamCodes\SeriesInformation;
use App\Http\Responses\XtreamCodes\VodInformation;
use App\Models\HttpClientConfig;
use App\Models\Series;
use App\Models\VodStream;
use App\Models\XtreamCodeConfig;
use Illuminate\Http\Client\ConnectionException;

/**
 * XtreamCodes Client Service
 *
 * @category Services
 *
 * @author shekohex <dev@shadykhalifa.me>
 * @license MIT
 */
readonly class XtreamCodesClient
{
    public function __construct(private XtreamCodeConfig $xtreamCodeConfig, private HttpClientConfig $httpClientConfig) {}

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
        } else {
            throw new UnauthorizedAccessException($response->getReasonPhrase());
        }
    }

    /**
     * Fetch Series from Xtream Codes API
     *
     * @return Series[]
     *
     * @throws ConnectionException
     */
    public function series(): array
    {
        $json = $this->do(XtreamCodesAction::GetSeries);

        return collect($json)->map(fn ($item) => new Series($item))->all();
    }

    /**
     * Perform an action on the Xtream Codes API
     *
     * @param  XtreamCodesAction  $action  The action to perform
     * @param  array  $params  Additional parameters for the action
     * @return mixed The JSON response from the API
     *
     * @throws ConnectionException
     */
    private function do(XtreamCodesAction $action, array $params = []): mixed
    {
        $client = $this->httpClientConfig->createClient()->baseUrl($this->xtreamCodeConfig->baseUrl());
        $response = $client->get('/player_api.php', array_merge($this->xtreamCodeConfig->credentialsWithAction($action), $params));

        return $response->json();
    }

    /**
     * Fetch Vod Streams from Xtream Codes API
     *
     * @return VodStream[]
     *
     * @throws ConnectionException
     */
    public function vodStreams(): array
    {
        $json = $this->do(XtreamCodesAction::GetVodStreams);

        return collect($json)->map(fn ($item) => new VodStream($item))->all();
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

        return SeriesInformation::fromJson($seriesId, $json);
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

        return VodInformation::fromJson($vodId, $json);
    }
}
