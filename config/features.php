<?php

declare(strict_types=1);

return [
    'direct_download_links' => env('FEATURE_DIRECT_DOWNLOAD_LINKS', false),
    'playback_link_ttl_minutes' => env('PLAYBACK_LINK_TTL_MINUTES', 30),
];
