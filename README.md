# cPanel WHM for Laravel

[![CI](https://github.com/itxshakil/cpanel-whm/actions/workflows/ci.yml/badge.svg)](https://github.com/itxshakil/cpanel-whm/actions/workflows/ci.yml)
[![Latest version](https://img.shields.io/packagist/v/itxshakil/cpanel-whm.svg)](https://packagist.org/packages/itxshakil/cpanel-whm)
[![Total downloads](https://img.shields.io/packagist/dt/itxshakil/cpanel-whm.svg)](https://packagist.org/packages/itxshakil/cpanel-whm)
[![PHPStan level max](https://img.shields.io/badge/PHPStan-level%20max-brightgreen.svg)](https://github.com/itxshakil/cpanel-whm/blob/main/phpstan.neon.dist)
[![WHM API coverage](https://img.shields.io/badge/WHM%20API%201-98.6%25%20typed-brightgreen.svg)](https://github.com/itxshakil/cpanel-whm/blob/main/docs/coverage.md)
[![UAPI coverage](https://img.shields.io/badge/UAPI-98.1%25%20typed-brightgreen.svg)](https://github.com/itxshakil/cpanel-whm/blob/main/docs/coverage.md)
[![License](https://img.shields.io/packagist/l/itxshakil/cpanel-whm.svg)](https://github.com/itxshakil/cpanel-whm/blob/main/LICENSE.md)

**Create, suspend and manage cPanel accounts from Laravel through the WHM API: typed methods for almost every documented WHM API 1 and UAPI function, typed results for the everyday ones, clear errors, a fake for your tests, and a `whm:doctor` command that tells you exactly why a connection doesn't work.**

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

## What's covered

| | |
| --- | --- |
| **Hand-written modules, typed results** | accounts, suspensions, packages, quotas, login sessions, server, DNS, domains, disk and bandwidth usage, backups and restores, resellers, SSL, API tokens (68 WHM functions) |
| **Generated, typed methods** | 621 of 630 WHM API 1 functions with `Whm::api()`, 680 of 693 UAPI functions with `Whm::asUser($user)->api()` ([coverage](https://github.com/itxshakil/cpanel-whm/blob/main/docs/coverage.md)) |
| **Anything else** | `Whm::call('function', [...])` and `Whm::asUser($user)->uapi('Module', 'function')` |

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
| Testing means mocking HTTP by hand | Use `Whm::fake()`, which goes through the real parsing and error paths, or replay real responses saved with `whm:record`. |
| Two people edit a DNS zone at once | DNS edits carry the zone's serial, so a stale edit fails instead of overwriting. |
| A network blip fails a page | Read-only calls are retried; changes never are, so nothing is created twice. |

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
$created->password;      // the one you gave, or a strong generated one

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

### DNS, domains, usage, backups, resellers, SSL, tokens

```php
use Itxshakil\CpanelWhm\Data\DnsRecord;

Whm::dns()->add('acme.example', DnsRecord::a('shop', '203.0.113.20'), DnsRecord::txt('@', 'v=spf1 -all'));
Whm::dns()->update('acme.example', Whm::dns()->zone('acme.example')->first('www', 'A')->withData('203.0.113.30'));

Whm::domains()->createSubdomain('blog.acme.example', 'public_html/blog');
Whm::domains()->owner('acme.example');                         // "acme"

Whm::usage()->account('acme')->disk?->percentUsed();           // 72.4
Whm::backups()->restore('acme', Whm::backups()->dates()->last());

Whm::resellers()->create('res1');
Whm::resellers()->setPrivileges('res1', ['create-acct', 'suspend-acct', 'list-accts']);

Whm::ssl()->autoSslProblems('acme');
Whm::tokens()->expiringWithin(14);
```

See [usage](https://github.com/itxshakil/cpanel-whm/blob/main/docs/usage.md) for every module.

### Every other function, typed

Generated from cPanel's OpenAPI documents, with named arguments, docblocks and links to cPanel's docs:

```php
Whm::api()->ipAddressManagement()->listips();
Whm::api()->sslCertificates()->fetchSslVhosts();
Whm::asUser('acme')->api()->email()->addPop(email: 'info', password: $password, quota: 1024);
Whm::asUser('acme')->api()->mysql()->createDatabase(name: 'acme_shop');
```

`php artisan whm:functions zone` finds the method for any function. See [the generated API](https://github.com/itxshakil/cpanel-whm/blob/main/docs/api.md).

### Any function by name

Every WHM API 1 function is also one call away, with the same error handling:

```php
use Itxshakil\CpanelWhm\Enums\HttpMethod;

$response = Whm::call('listips');
$response->get('ip.0.ip');           // dot notation into data
$response->warnings();               // warnings WHM attached to a "successful" call

Whm::call('modifyacct', $params, HttpMethod::Post);
```

Without a method, `Whm::call()` uses the one cPanel documents for the function, and any call with a password, token or key is sent as POST.

`php artisan whm:functions --server --missing` shows what your server offers beyond the spec (plugin functions, for example).

### Several calls in one request

```php
$results = Whm::batch()->add('accountsummary', ['user' => 'acme'])->add('version')->send();
$results[0]->get('acct.0.domain');
```

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
| `WhmConnectionFailed` | DNS, refused connection, TLS failure or timeout. `mayHaveReachedServer()` says whether WHM may have acted before it stopped answering. |
| `WhmAuthenticationFailed` | HTTP 401/403: the token was rejected. |
| `WhmHttpError` | Another HTTP error, or an answer that isn't JSON (wrong port or a proxy). |
| `WhmCommandFailed` | WHM ran the function and reported failure. Has `reason()`, `function()`, `response()`, `rawOutput()`. |
| `WhmPermissionDenied` | Like `WhmCommandFailed`, but the token's ACL doesn't allow the function. |
| `UapiCallFailed` | The UAPI function inside `uapi_cpanel` (or `cpanel`) failed. Has `errors()`. |
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

Unfaked calls throw `StrayWhmCall`, so a test can never reach a real server. There are also `Whm::sequence()`, `Whm::connectionError()`, `Whm::httpError(401)`, `Whm::uapi([...])`, `Whm::uapiFailure('...')` and `Whm::fixture('tests/Fixtures/whm/listaccts.json')` (saved with `php artisan whm:record`), closures, and `assertCalledTimes` / `assertSentCount` / `assertNothingSent`. See [docs/testing.md](https://github.com/itxshakil/cpanel-whm/blob/main/docs/testing.md).

## Artisan commands

| Command | What it does |
| --- | --- |
| `whm:install` | Set up the connection interactively, then run the doctor |
| `whm:doctor` (`whm:test`) | Check a connection step by step (`--connection`, `--all`, `--json`) |
| `whm:call {function} {key=value…}` | Run any function (`--json`, `--dry-run`, `--post`); secrets always go as POST, destructive functions ask first |
| `whm:functions {search?}` | Find the method for any function (`--uapi`, `--curated`, `--server --missing`) |
| `whm:accounts` | List accounts (`--search`, `--by`, `--package`, `--suspended`) |
| `whm:account {user}` | One account's summary |
| `whm:login {user}` | Print a one-time login link (`--service`, `--app`) |
| `whm:suspend {user}` / `whm:unsuspend {user}` | Suspend (`--reason`, `--lock`) or unsuspend an account |
| `whm:packages` | Packages and their limits (`--json`) |
| `whm:dns {domain?}` | Zones, or one zone's records with line numbers (`--type`) |
| `whm:token` | API tokens and their expiry; fails when one expires within `--days` |
| `whm:usage` | Accounts near their disk or bandwidth limit; fails when one is at or above `--threshold` |
| `whm:record {function} {key=value…}` | Save a redacted real response as a test fixture |

`php artisan about` also shows a cPanel WHM section.

## Events, retries and caching

The modules dispatch `AccountCreated`, `AccountRemoved`, `AccountSuspended`, `AccountUnsuspended`, `AccountPackageChanged`, `AccountModified`, `AccountPasswordChanged` and `DnsZoneChanged` once WHM confirms the change: a ready-made feed for an audit log.

Read-only calls are retried after a connection error or HTTP 5xx, and `Whm::cache(300)->packages()->list()` caches them. Calls that change something are never retried or cached.

Set `WHM_LOG_CHANNEL=stack` to log every call: function, connection, duration and outcome. Parameters are redacted (passwords, tokens and login URLs never reach the log). `WhmRequestSending`, `WhmResponseReceived` and `WhmRequestFailed` fire for every call, with secrets already masked.

With spatie/laravel-health, add `WhmCheck::new()->failWhenTokenExpiresWithin(7)` to your checks.

## Documentation

- [Configuration](https://github.com/itxshakil/cpanel-whm/blob/main/docs/configuration.md)
- [The modules: accounts, DNS, domains, usage, backups, resellers, SSL, tokens, ...](https://github.com/itxshakil/cpanel-whm/blob/main/docs/usage.md)
- [The generated API for every other function](https://github.com/itxshakil/cpanel-whm/blob/main/docs/api.md)
- [UAPI through WHM](https://github.com/itxshakil/cpanel-whm/blob/main/docs/uapi.md)
- [whm:doctor and the other commands](https://github.com/itxshakil/cpanel-whm/blob/main/docs/commands.md)
- [Errors](https://github.com/itxshakil/cpanel-whm/blob/main/docs/errors.md)
- [Testing](https://github.com/itxshakil/cpanel-whm/blob/main/docs/testing.md)
- [API coverage](https://github.com/itxshakil/cpanel-whm/blob/main/docs/coverage.md)
- [Roadmap](https://github.com/itxshakil/cpanel-whm/blob/main/docs/roadmap.md)

## Security

Only API tokens are supported. The token is sent in the `Authorization` header and nowhere else. Report vulnerabilities privately; see [SECURITY.md](https://github.com/itxshakil/cpanel-whm/blob/main/SECURITY.md).

## License

MIT. See [LICENSE.md](https://github.com/itxshakil/cpanel-whm/blob/main/LICENSE.md).
