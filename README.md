# cPanel WHM for Laravel

[![CI](https://github.com/itxshakil/cpanel-whm/actions/workflows/ci.yml/badge.svg)](https://github.com/itxshakil/cpanel-whm/actions/workflows/ci.yml)
[![Latest version](https://img.shields.io/packagist/v/itxshakil/cpanel-whm.svg)](https://packagist.org/packages/itxshakil/cpanel-whm)
[![Total downloads](https://img.shields.io/packagist/dt/itxshakil/cpanel-whm.svg)](https://packagist.org/packages/itxshakil/cpanel-whm)
[![PHPStan level max](https://img.shields.io/badge/PHPStan-level%20max-brightgreen.svg)](phpstan.neon.dist)
[![License](https://img.shields.io/packagist/l/itxshakil/cpanel-whm.svg)](LICENSE.md)

**Create, suspend and manage cPanel accounts from Laravel through the WHM API, with typed results, clear errors, a fake for your tests, and a `whm:doctor` command that tells you exactly why a connection doesn't work.**

```php
use Itxshakil\CpanelWhm\Data\NewAccount;
use Itxshakil\CpanelWhm\Facades\Whm;

$account = Whm::accounts()->create(new NewAccount(
    username: 'acme',
    domain: 'acme.example',
    package: 'starter',
));

Whm::suspensions()->suspend('acme', 'Unpaid invoice #1042');

return redirect()->away(Whm::sessions()->create('acme')->url);   // one-click cPanel login
```

It's for hosting resellers, agencies and anyone building their own hosting billing or provisioning on Laravel.

---

## Why this package

Most WHM clients for PHP were written for Laravel 5, log in with a password or the access hash that cPanel deprecated in version 64, return raw arrays, and can't be faked in tests. This one is built from a production hosting platform's client and fixes the things that bit it:

| Problem | What this package does |
| --- | --- |
| WHM answers HTTP 200 even when a function fails | `metadata.result = 0` throws `WhmCommandFailed` with WHM's reason. |
| Some failures put the reason in `data`, leaving `metadata` empty | The reason is found either way. You never get an alert that just says "OK". |
| `uapi_cpanel` succeeds while the UAPI function inside it fails | That raises `UapiCallFailed` with the UAPI errors. |
| A token without the right ACL fails with a vague message | You get `WhmPermissionDenied`, and `whm:doctor` lists the missing privileges. |
| WHM renames an account on a username collision | `create()` returns the username WHM actually assigned. |
| Passwords leak into URLs, logs and cURL errors | Secrets go in POST bodies; logs, events and errors are redacted. |
| Testing means mocking HTTP by hand | Use `Whm::fake()`, which goes through the real parsing and error paths. |

## Requirements

- PHP 8.3 or newer
- Laravel 12 or 13
- cPanel & WHM with an API token (WHM › Development › Manage API Tokens)

## Installation

```bash
composer require itxshakil/cpanel-whm
php artisan whm:install
```

`whm:install` asks for the host, user and token, writes them to `.env`, publishes `config/cpanel-whm.php` and runs `whm:doctor`. To configure by hand instead:

```dotenv
WHM_HOST=server.example.com     # https and port 2087 are assumed
WHM_USER=root                   # or a reseller
WHM_TOKEN=your-api-token
WHM_VERIFY_TLS=true             # false only for a server reached by IP with a self-signed certificate
```

## Check the connection

```bash
php artisan whm:doctor
```

```
  WHM connection · main · https://server.example.com:2087

  ✔ Config      root @ https://server.example.com:2087, TLS verification on
  ✔ DNS         server.example.com → 203.0.113.10
  ✔ Network     port 2087 open (38 ms)
  ✔ TLS         certificate trusted, valid until 2027-01-14
  ✖ Auth        WHM returned HTTP 401 for version.
    → The token was rejected: it may be mistyped, expired or revoked, or belong to another user. ...
  · Privileges  skipped: an earlier check failed
```

The checks run in order: config, DNS, the port, TLS, the token, its privileges, the functions your app needs, the server version and latency. The first failure stops the run and says what to fix. Use `--json` in CI, `--all` for every connection, or call `Whm::ping()` from your own health page.

## Usage

### Accounts

```php
use Itxshakil\CpanelWhm\Enums\SearchType;
use Itxshakil\CpanelWhm\Support\Filter;

$created = Whm::accounts()->create(new NewAccount('acme', 'acme.example', package: 'starter'));
$created->username;      // what WHM assigned
$created->nameservers;   // ['ns1.example.com', 'ns2.example.com']

Whm::accounts()->list();                                         // Collection<Account>
Whm::accounts()->list(Filter::where('domain', 'contains', 'acme'));
Whm::accounts()->search('starter', SearchType::Package, exact: true);
Whm::accounts()->find('acme');                                   // ?Account
Whm::accounts()->isUsernameAvailable('acme');                    // local rules, then WHM
Whm::accounts()->changePackage('acme', 'pro');
Whm::accounts()->changePassword('acme', $password);
Whm::accounts()->modify('acme', ['CONTACTEMAIL' => 'new@acme.example']);
Whm::accounts()->remove('acme');
```

Usernames are checked against cPanel's rules before any request: lowercase letters and digits, no leading digit, not starting with "test", at most 16 characters, and not a reserved name. A bad name throws `InvalidUsername` with the rule it broke.

### Suspensions, packages, quotas

```php
Whm::suspensions()->suspend('acme', 'Unpaid', lock: true);   // lock: the reseller cannot unsuspend
Whm::suspensions()->unsuspend('acme');
Whm::suspensions()->list();                                  // Collection<SuspendedAccount>

Whm::packages()->list();                                     // Collection<Package>
Whm::packages()->find('starter')?->diskQuota;

Whm::quotas()->setDiskQuota('acme', 10_240);                 // MB, null = unlimited
Whm::quotas()->setBandwidthLimit('acme', null);
```

### Login links

```php
use Itxshakil\CpanelWhm\Enums\SessionService;

$session = Whm::sessions()->create('acme', SessionService::Cpanel, app: 'Email_Accounts');
$session->url;         // one use; the session ends after 15 minutes idle
$session->expiresAt;
```

### cPanel (UAPI) functions as an account

UAPI calls run through WHM's `uapi_cpanel` with your WHM token, so you don't need a cPanel token per account.

```php
$mailboxes = Whm::asUser('acme')->uapi('Email', 'list_pops');
$mailboxes->toArray();
$mailboxes->warnings;

Whm::asUser('acme')->uapi('Email', 'add_pop', ['email' => 'info', 'password' => $password]);
```

### Any other function

Every WHM API 1 function is one call away, with the same error handling:

```php
use Itxshakil\CpanelWhm\Enums\HttpMethod;

$response = Whm::call('listips');
$response->get('ip.0.ip');           // dot notation into data
$response->warnings();               // warnings WHM attached to a "successful" call

Whm::call('installssl', $params, HttpMethod::Post);
```

`php artisan whm:functions` lists the functions with typed methods; `--server --missing` shows what your server offers beyond them.

### Several servers

```php
// config/cpanel-whm.php → 'connections' => ['main' => [...], 'ca-1' => [...]]
Whm::connection('ca-1')->accounts()->list();
```

Inject `Itxshakil\CpanelWhm\Contracts\WhmClient` to get the default connection.

## Errors

Everything extends `Itxshakil\CpanelWhm\Exceptions\WhmException`, and each has a `hint()` with what to do next.

| Exception | When |
| --- | --- |
| `WhmConnectionFailed` | DNS, refused connection, TLS failure or timeout. Nothing reached WHM. |
| `WhmAuthenticationFailed` | HTTP 401/403: the token was rejected. |
| `WhmHttpError` | Another HTTP error, or an answer that isn't JSON (wrong port or a proxy). |
| `WhmCommandFailed` | WHM ran the function and reported failure. Has `reason()`, `function()`, `response()`, `rawOutput()`. |
| `WhmPermissionDenied` | Like `WhmCommandFailed`, but the token's ACL doesn't allow the function. |
| `UapiCallFailed` | The UAPI function inside `uapi_cpanel` failed. Has `errors()`. |
| `InvalidUsername` | A username broke a cPanel rule (checked before sending). |
| `InvalidConfiguration` | Missing host or token, unknown connection, or a cPanel port. |

## Testing your app

```php
use Itxshakil\CpanelWhm\Facades\Whm;

public function test_an_overdue_invoice_suspends_the_account(): void
{
    $whm = Whm::fake([
        'suspendacct' => Whm::response(),
        'accountsummary' => Whm::failure('Account does not exist.'),
    ]);

    // ... run your code ...

    $whm->assertCalled('suspendacct', fn (array $params) => $params['user'] === 'acme');
    $whm->assertNotCalled('removeacct');
}
```

Unfaked calls throw `StrayWhmCall`, so a test can never reach a real server. There are also `Whm::sequence()`, `Whm::connectionError()`, `Whm::httpError(401)`, `Whm::uapi([...])` and `Whm::uapiFailure('...')`, closures, and `assertCalledTimes` / `assertSentCount` / `assertNothingSent`. See [docs/testing.md](docs/testing.md).

## Artisan commands

| Command | What it does |
| --- | --- |
| `whm:install` | Set up the connection interactively, then run the doctor |
| `whm:doctor` (`whm:test`) | Check a connection step by step (`--connection`, `--all`, `--json`) |
| `whm:call {function} {key=value…}` | Run any function (`--post`, `--json`, `--dry-run`); destructive functions ask first |
| `whm:functions {search?}` | Typed methods per function; `--server --missing` for the rest |
| `whm:accounts` | List accounts (`--search`, `--by`, `--package`, `--suspended`) |
| `whm:account {user}` | One account's summary |
| `whm:login {user}` | Print a one-time login link (`--service`, `--app`) |

`php artisan about` also shows a cPanel WHM section.

## Logging and events

Set `WHM_LOG_CHANNEL=stack` to log every call: function, connection, duration and outcome. Parameters are redacted (passwords, tokens and login URLs never reach the log). For anything custom, listen to `WhmRequestSending`, `WhmResponseReceived` and `WhmRequestFailed`.

## Documentation

- [Configuration](docs/configuration.md)
- [Accounts, suspensions, packages and sessions](docs/usage.md)
- [UAPI through WHM](docs/uapi.md)
- [whm:doctor and the other commands](docs/commands.md)
- [Errors](docs/errors.md)
- [Testing](docs/testing.md)
- [Roadmap and API coverage](docs/roadmap.md)

## Security

Only API tokens are supported. The token is sent in the `Authorization` header and nowhere else. Report vulnerabilities privately; see [SECURITY.md](SECURITY.md).

## License

MIT. See [LICENSE.md](LICENSE.md).
