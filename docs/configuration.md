# Configuration

Publish the config with `php artisan vendor:publish --tag=cpanel-whm-config` (or let `whm:install` do it).

## Connections

```php
'default' => env('WHM_CONNECTION', 'main'),

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
```

| Key | Notes |
| --- | --- |
| `host` | `server.example.com` (https and port 2087 are assumed), or a full URL such as `https://203.0.113.5:2087`. Ports 2082, 2083, 2095 and 2096 serve cPanel and Webmail and are rejected: WHM API 1 does not answer there. |
| `user` | The user the token belongs to: `root` or a reseller. |
| `token` | A WHM API token. Passwords and the access hash (deprecated in cPanel & WHM 64) are not supported. |
| `verify_tls` | Keep `true`. Set `false` only for a server reached by IP with a self-signed certificate; `whm:doctor` warns about it. |
| `timeout` | Seconds per call. `createacct` always gets at least 120 seconds, because it sets up DNS, mail and DKIM. |
| `connect_timeout` | Seconds to open the connection. |

Add more servers as more entries and pick one with `Whm::connection('name')` or `--connection=name`.

## Creating the token

In WHM: **Development › Manage API Tokens › Generate Token**. Give it only the privileges your app needs, and set an expiry. Copy it immediately: WHM will not show it again. Expired tokens are not deleted automatically.

## Retries

```php
'connections' => [
    'main' => [
        // ...
        'retry' => [
            'times' => (int) env('WHM_RETRY_TIMES', 2),
            'sleep_ms' => (int) env('WHM_RETRY_SLEEP_MS', 250),
        ],
    ],
],
```

Only read-only functions are retried, and only after a connection error or an HTTP 5xx. The wait grows with each attempt (250 ms, then 500 ms). Set `times` to `0` to turn retries off.

## Cache

```php
'cache' => [
    'store' => env('WHM_CACHE_STORE'),   // null: the default cache store
    'prefix' => 'cpanel-whm',
],
```

Used by `Whm::cache($ttl)`. Keys include the connection, the function and its parameters.

## Logging

```php
'log_channel' => env('WHM_LOG_CHANNEL'),
'log_responses' => (bool) env('WHM_LOG_RESPONSES', false),
'log_levels' => ['success' => 'info', 'command_failed' => 'warning', 'error' => 'warning'],
```

When `log_channel` is set, every call is logged to that channel with the function, connection, duration and redacted parameters. The level depends on the outcome: `success`, `command_failed` (WHM or UAPI answered and said no) or `error` (an HTTP or connection error). Change them in `log_levels`, e.g. `debug` for successes and `error` for errors.

With `WHM_LOG_RESPONSES=true` each entry also carries WHM's answer (the reason, warnings, data, or UAPI's errors), redacted the same way as the parameters. It's off by default because answers can be large and hold customer data.

## whm:doctor requirements

```php
'doctor' => [
    'required_privileges' => ['create-acct', 'suspend-acct', 'kill-acct'],
    'required_functions' => ['createacct', 'uapi_cpanel'],
    'minimum_version' => '11.110',
],
```

`whm:doctor` fails when the token lacks a listed privilege (from `myprivs`) or the server lacks a listed function (from `applist`), and warns when the server is older than `minimum_version`.
