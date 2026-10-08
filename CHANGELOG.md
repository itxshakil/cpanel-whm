# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.1.0] - 2026-10-08

First release, extracted from a production hosting platform's WHM client.

### Client
- Token-only WHM API 1 client on Laravel's HTTP client, with named connections, `api.version=1` on every call, configurable TLS verification and timeouts.
- `Whm::call()` uses the HTTP method cPanel documents for each function (POST for undocumented ones), and any call with a password, token, passphrase or key is always sent as POST.
- Parameters: nulls are left out, booleans sent as 1/0, lists as WHM's repeated parameters (`name`, `name-1`, ...).
- `WhmResponse`, with the `data.reason` fallback, warnings hidden in successful calls, raw output and dot-notation access.
- Typed errors, each with a `hint()`: `WhmConnectionFailed`, `WhmHttpError`, `WhmAuthenticationFailed`, `WhmCommandFailed`, `WhmPermissionDenied`, `UapiCallFailed`, `InvalidUsername` and `InvalidConfiguration`.
- `WhmConnectionFailed::mayHaveReachedServer()` tells a read timeout or an empty reply (WHM may have acted) from a failed connection.
- `uapi_cpanel` and `cpanel` calls throw `UapiCallFailed` when the cPanel function inside them fails, including through `Whm::call()`.
- Works under Octane: each request's clients are built from that request's application.

### Security
- Messages never contain the token, passwords or query strings, and connection errors don't chain the original exception (which holds the URL and headers).
- Events (`WhmRequestSending`, `WhmResponseReceived`, `WhmRequestFailed`) carry redacted copies of the request and response, so passwords, new API tokens and login URLs never reach Telescope, queued listeners or logs.
- Optional redacted request logging (`WHM_LOG_CHANNEL`).
- Secret parameters are marked `#[SensitiveParameter]` in the client and in every generated method.

### Every documented function
- `Whm::api()`: a typed method for 621 of WHM API 1's 630 documented functions, grouped like cPanel's docs, with named arguments, docblocks and links.
- `Whm::asUser($user)->api()`: a typed method for 680 of 693 UAPI functions, one accessor per module.
- `bin/generate` (`composer generate`) rebuilds them, `FunctionCatalog` and `docs/coverage.md` from cPanel's OpenAPI specs.

### Modules
- `accounts()`: create, list, search, find, exists, username availability, password, package, modify, remove.
- `suspensions()`, `quotas()`, `sessions()` (one-time login links) and `server()` (version, functions, privileges, hostname, load average, service status, restarts, partitions).
- `packages()`: list, find, info, create, update and delete (`PackageDefinition`).
- `dns()`: zones, parsed records with `DnsRecord` / `DnsZone`, and serial-checked add, update and remove through `mass_edit_dns_zone`.
- `domains()`: domain info and owners, subdomains, aliases, delete.
- `usage()`: disk and bandwidth per account (`DiskUsage`, `BandwidthUsage`, `AccountUsage`).
- `backups()`: backup dates and users, restore queue, background pkgacct.
- `resellers()`: create, remove, privileges and ACL lists, limits, stats, suspend, terminate.
- `ssl()`: AutoSSL runs and problems, certificate install.
- `tokens()`: list, create and revoke API tokens; expiry checks.
- UAPI as an account through `uapi_cpanel` with `Whm::asUser()`.
- Local cPanel username rules (`UsernameRules`) and WHM output filters (`Filter`). Modules are macroable.

### Operations
- Domain events: `AccountCreated`, `AccountRemoved`, `AccountSuspended`, `AccountUnsuspended`, `AccountPackageChanged`, `AccountPasswordChanged`, `DnsZoneChanged`.
- Read-only functions are retried after connection errors and HTTP 5xx (`retry` per connection); changes never are.
- `Whm::cache($ttl)` caches read-only calls, keyed by server and user; `withoutCache()` returns an uncached copy. Username checks are never cached and DNS edits read the zone serial uncached.
- `WhmCheck` for spatie/laravel-health.

### Console
- `whm:install`, `whm:doctor` (alias `whm:test`), `whm:call`, `whm:functions`, `whm:accounts`, `whm:account`, `whm:login`, `whm:suspend`, `whm:unsuspend`, `whm:packages`, `whm:dns`, `whm:token` (fails when a token expires soon) and `whm:record` (save a redacted fixture), plus an `about` section.
- `Whm::ping()` returns the doctor's `ConnectionReport`.

### Testing
- `Whm::fake()` with response helpers, sequences, closures, stray-call protection and assertions; `Whm::fixture()` replays responses saved with `whm:record`. Faked retries don't sleep.

### Docs
- README, guides for configuration, usage, the generated API, UAPI, commands, errors, testing and coverage, and Laravel Boost guidelines.
