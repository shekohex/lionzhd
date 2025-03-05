<?php

namespace App\Enums;

/**
 * XtreamCodes Actions
 *
 * @category Enums
 *
 * @author shekohex <dev@shadykhalifa.me>
 */
enum XtreamCodesAction: string
{
    /**
     * Get Series from Xtream Codes API
     */
    case GetSeries = 'get_series';
    /**
     * Get VOD Streams from Xtream Codes API
     */
    case GetVodStreams = 'get_vod_streams';

    /**
     * Get Series Info from Xtream Codes API
     */
    case GetSeriesInfo = 'get_series_info';

    /**
     * Get VOD Info from Xtream Codes API
     */
    case GetVodInfo = 'get_vod_info';
}
