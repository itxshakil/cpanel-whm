<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default connection
    |--------------------------------------------------------------------------
    |
    | The connection Whm::accounts(), Whm::call() and the injected WhmClient
    | use. Pick another one with Whm::connection('name') or --connection=.
    |
    */

    'default' => env('WHM_CONNECTION', 'main'),

    /*
    |--------------------------------------------------------------------------
    | Connections
    |--------------------------------------------------------------------------
    |
    | One entry per WHM server. Only API tokens are supported: create one in
    | WHM > Development > Manage API Tokens. The host may be a bare hostname
    | (https and port 2087 are assumed) or a full URL. cPanel ports
    | (2082/2083) and Webmail ports (2095/2096) are rejected: WHM API 1 does
    | not answer there.
    |
    */

    'connections' => [

        'main' => [
            'host' => env('WHM_HOST'),
            'user' => env('WHM_USER', 'root'),
            'token' => env('WHM_TOKEN'),
            'verify_tls' => (bool) env('WHM_VERIFY_TLS', true),
            'timeout' => (int) env('WHM_TIMEOUT', 30),
            'connect_timeout' => (int) env('WHM_CONNECT_TIMEOUT', 10),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Request logging
    |--------------------------------------------------------------------------
    |
    | Off by default. Set a log channel name to log every WHM call (function,
    | connection, duration, outcome). Parameters are redacted: passwords,
    | tokens and login URLs never reach the log.
    |
    */

    'log_channel' => env('WHM_LOG_CHANNEL'),

    /*
    |--------------------------------------------------------------------------
    | whm:doctor
    |--------------------------------------------------------------------------
    |
    | List the privileges (from WHM's myprivs, e.g. "create-acct") and the
    | functions (e.g. "createacct") your app relies on, and whm:doctor will
    | fail when the token or the server lacks them.
    |
    */

    'doctor' => [
        'required_privileges' => [],
        'required_functions' => [],
        'minimum_version' => '11.110',
    ],

];
