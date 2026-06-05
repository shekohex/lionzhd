<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default User Agent
    |--------------------------------------------------------------------------
    |
    | This value specifies the default user agent that Saloon will use
    | for all requests. You can change this value to whatever you
    | would like. This value can also be overridden on a
    | per-connector basis.
    |
    */

    'default_user_agent' => env('HTTP_CLIENT_USER_AGENT', 'Saloon/1.0'),
];
