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

            // A self-signed or private-CA certificate can stay verified: point
            // ca_bundle at the CA (or the certificate itself) when you connect
            // by hostname, or pin the public key (sha256//..., as whm:doctor
            // prints it) when you connect by IP with verify_tls off.
            'ca_bundle' => env('WHM_CA_BUNDLE'),
            'pinned_public_key' => env('WHM_PINNED_PUBLIC_KEY'),

            'timeout' => (int) env('WHM_TIMEOUT', 30),
            'connect_timeout' => (int) env('WHM_CONNECT_TIMEOUT', 10),

            // The server's timezone, e.g. America/Toronto. Backup dates given
            // as date objects are converted to it before the day is taken.
            'timezone' => env('WHM_TIMEZONE'),

            // Read-only functions (listaccts, accountsummary, ...) are retried
            // after a connection error or an HTTP 5xx. Changes never are.
            'retry' => [
                'times' => (int) env('WHM_RETRY_TIMES', 2),
                'sleep_ms' => (int) env('WHM_RETRY_SLEEP_MS', 250),
            ],
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
    | log_responses adds WHM's answer to each entry (redacted the same way),
    | which is where the reason, the warnings and UAPI's errors are. The
    | levels apply to a success, to WHM or UAPI saying no, and to HTTP or
    | connection errors.
    |
    */

    'log_channel' => env('WHM_LOG_CHANNEL'),

    'log_responses' => (bool) env('WHM_LOG_RESPONSES', false),

    'log_levels' => [
        'success' => 'info',
        'command_failed' => 'warning',
        'error' => 'warning',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | The store Whm::cache($ttl) uses for read-only calls, e.g.
    | Whm::cache(300)->accounts()->list(). null means the default store.
    |
    */
    'cache' => [
        'store' => env('WHM_CACHE_STORE'),
        'prefix' => 'cpanel-whm',
    ],

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
