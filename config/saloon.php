<?php

declare(strict_types=1);

use Saloon\HttpSender\HttpSender;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Saloon Sender
    |--------------------------------------------------------------------------
    |
    | This value specifies the "sender" class that Saloon should use by
    | default on all connectors. You can change this sender if you
    | would like to use your own. You may also specify your own
    | sender on a per-connector basis.
    |
    */

    'default_sender' => HttpSender::class,

    /*
    |--------------------------------------------------------------------------
    | Integrations Path
    |--------------------------------------------------------------------------
    |
    | By default, this package will create any classes within
    | `/app/Http/Integrations` directory. If you're using
    | a different design approach, then your classes
    | may be in a different place. You can change
    | that location so that the saloon:list
    | command will still find your files
    |
    */

    'integrations_path' => base_path('App/Http/Integrations'),

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
