<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    /*
    |--------------------------------------------------------------------------
    | Aria2 RPC
    |--------------------------------------------------------------------------
    |
    | These configuration options determine how to connect to the Aria2 RPC.
    | Configure the RPC endpoint and authentication details here.
    |
    |
    */
    'aria2' => [
        'host' => env('ARIA2_RPC_HOST', 'http://localhost'),
        'port' => env('ARIA2_RPC_PORT', 6800),
        'secret' => env('ARIA2_RPC_SECRET', null),
    ],
    /*
    |--------------------------------------------------------------------------
    | Xtream Codes API
    |--------------------------------------------------------------------------
    |
    | These configuration options determine how to connect to the Xtream Codes API.
    | Configure the API endpoint and authentication details here.
    |
    |
    */
    'xtream' => [
        'host' => env('XTREAM_CODES_API_HOST', 'http://api.xtream-codes.com'),
        'port' => env('XTREAM_CODES_API_PORT', 80),
        'username' => env('XTREAM_CODES_API_USER', 'example'),
        'password' => env('XTREAM_CODES_API_PASS', 'password'),
    ],

];
