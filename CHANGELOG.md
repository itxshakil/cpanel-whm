# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.2.0] - unreleased

### Every documented function
- `Whm::api()`: a typed method for 621 of WHM API 1's 630 documented functions, grouped like cPanel's docs, with named arguments, docblocks and links.
- `Whm::asUser($user)->api()`: a typed method for 680 of 693 UAPI functions, one accessor per module.
- `bin/generate` (`composer generate`) rebuilds them, `FunctionCatalog` and `docs/coverage.md` from cPanel's OpenAPI specs.
- Parameters: nulls are left out, booleans sent as 1/0, lists as WHM's repeated parameters (`name`, `name-1`, ...). This applies to `Whm::call()` too.

### New modules
- `dns()`: zones, parsed records with `DnsRecord` / `DnsZone`, and serial-checked add, update and remove through `mass_edit_dns_zone`.
- `domains()`: domain info and owners, subdomains, aliases, delete.
- `usage()`: disk and bandwidth per account (`DiskUsage`, `BandwidthUsage`, `AccountUsage`).
- `backups()`: backup dates and users, restore queue, background pkgacct.
- `resellers()`: create, remove, privileges and ACL lists, limits, stats, suspend, terminate.
- `ssl()`: AutoSSL runs and problems, certificate install.
- `tokens()`: list, create and revoke API tokens; expiry checks.
- `packages()` can now create, update and delete (`PackageDefinition`) and read one package with `info()`.
- `server()` adds load average, service status, restarts and partitions.

### Operations
- Domain events: `AccountCreated`, `AccountRemoved`, `AccountSuspended`, `AccountUnsuspended`, `AccountPackageChanged`, `AccountPasswordChanged`, `DnsZoneChanged`.
- Read-only functions are retried after connection errors and HTTP 5xx (`retry` per connection).
- `Whm::cache($ttl)` caches read-only calls.
- `WhmCheck` for spatie/laravel-health.

### Console
- `whm:suspend`, `whm:unsuspend`, `whm:packages`, `whm:dns`, `whm:token` (fails when a token expires soon) and `whm:record` (save a redacted fixture; replay it with `Whm::fixture()`).
- `whm:functions` searches every WHM and UAPI function (`--uapi`, `--curated`).

### Changed
- `suspendacct`, `unsuspendacct` and `changepackage` are sent as POST.

## [0.1.0] - unreleased

First release, extracted from a production hosting platform's WHM client.

### Client
- Token-only WHM API 1 client on Laravel's HTTP client, with named connections, POST bodies for secrets, `api.version=1` on every call, configurable TLS verification and timeouts.
- `WhmResponse`, with the `data.reason` fallback, warnings hidden in successful calls, raw output and dot-notation access.
- Typed errors, each with a `hint()`: `WhmConnectionFailed`, `WhmHttpError`, `WhmAuthenticationFailed`, `WhmCommandFailed`, `WhmPermissionDenied`, `UapiCallFailed`, `InvalidUsername` and `InvalidConfiguration`. Messages never contain the token, passwords or query strings.
- Events (`WhmRequestSending`, `WhmResponseReceived`, `WhmRequestFailed`) and optional redacted request logging (`WHM_LOG_CHANNEL`).

### WHM functions
- Accounts: create, list, search, find, exists, username availability, password, package, modify, remove.
- Suspensions, packages, disk and bandwidth quotas, one-time login sessions, and server info (version, functions, privileges, hostname).
- UAPI as an account through `uapi_cpanel`.
- Local cPanel username rules (`UsernameRules`) and WHM output filters (`Filter`). Modules are macroable.

### Console
- `whm:install`, `whm:doctor` (alias `whm:test`), `whm:call`, `whm:functions`, `whm:accounts`, `whm:account` and `whm:login`, plus an `about` section.
- `Whm::ping()` returns the doctor's `ConnectionReport`.

### Testing
- `Whm::fake()` with response helpers, sequences, closures, stray-call protection and assertions.

### Docs
- README, seven guides and Laravel Boost guidelines.
