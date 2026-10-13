# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- `AccountNotFound` (extends `WhmCommandFailed`): thrown by every call when WHM says the named account doesn't exist, including the "You do not have a user named …" wording of `suspendacct`, `modifyacct` and `removeacct`. `Whm::accountNotFound()` fakes it.
- `Accounts::removeIfExists()`: removes an account, or returns false when it is already gone, so a retried job doesn't fail.
- `Accounts::createOrRecover()`: like `create()`, but after a timeout that may have reached WHM it looks the account up and returns it (`CreatedAccount::$recovered`), with the password the request carried, when it exists with the requested domain.
- `WhmClient::flushCache()` (and `Whm::flushCache()`): forget every cached read for the server.
- `Whm::build(array $config, string $name = 'runtime')`: a client for a server that isn't in config (for example, one read from a database), validated like a configured connection and covered by `Whm::fake()`.
- `WhmClient::timeout(int $seconds, ?int $connect = null)` (and `Whm::timeout()`): a copy of the client with other timeouts, for status pages and health checks. `ConnectionConfig::withTimeouts()` backs it. The `WhmClient` contract gains `timeout()`.
- `ca_bundle` (`WHM_CA_BUNDLE`): verify WHM's certificate against your own CA file instead of turning verification off.
- `pinned_public_key` (`WHM_PINNED_PUBLIC_KEY`): calls fail unless the certificate's public key matches, even with `verify_tls` off. `whm:doctor` checks the pin and prints the server's.
- `Whm::timedOutAfterSending()`: fakes a read timeout after the call was sent, so `mayHaveReachedServer()` paths can be tested without a magic cURL string.
- `WhmFake::assertUapiCalled()` and `assertUapiNotCalled()`: assert UAPI calls by account, module and function.
- `NewAccount`: typed `customIp`, `quotaMb`, `bandwidthMb`, `featureList`, `owner`, `maxEmailsPerHour`, `language` and `shell`.
- `Account`: `suspendedAt`, `locked`, `ipv6`, `backupEnabled`, `shell`, `theme`, `partition`, `inodesUsed`, `inodesLimit` and the per-account limits.
- `Package`: `maxAliases`, `maxMailingLists`, `maxEmailsPerHour`, `maxEmailQuota`, `theme`, `language`, `shell`, `cgi`, and `toDefinition()` to save a package elsewhere without losing settings.
- `Whm::featureLists()`: `list()`, `features()`, `create()`, `update()` (refuses an empty change, which WHM would read as "turn everything off") and `delete()`.
- `Accounts::changePrimaryDomain()`, `rename()` (checks the new username first) and `changeIp()` (`setsiteip`), each dispatching `AccountModified`.
- The `WhmClient` contract gains `featureLists()`.
- An opt-in live test suite (`tests/Live`, `composer test:live`, the manual "Live WHM" workflow) that checks reads, batch encoding, the failed-UAPI response shape and, with `WHM_LIVE_WRITE=1`, an account round trip against a real WHM. It is skipped unless `WHM_LIVE_HOST` and `WHM_LIVE_TOKEN` are set.

### Changed
- Parameters: backed enums are sent as their value and `Stringable` objects as their string. A nested array, a date or any other object throws `InvalidArgumentException` before anything is sent, instead of reaching WHM as `name[key]=…` and being ignored.
- A successful call that changes something drops that server's cached reads, so `Whm::cache()` never returns data from before your own change. Cache keys gained a generation segment.
- The `WhmClient` contract gains `flushCache()`.
- `NetworkProbe::certificate()` takes an optional `$caBundle`, and `CertificateInfo` gains `publicKeyPin`. Custom probes need the new parameter.
- The Release workflow runs the full CI (lint, PHPStan, Composer validation and the test matrix) on the tagged commit before validating the install.
- Cache keys sort the parameters, so the same read with its parameters in another order hits the same cache entry.

### Fixed
- `whm:record` prints a replay hint with the path relative to the project root, so it can be committed.
- `Whm::fixture()` treats Windows (`C:\…`, `C:/…`) and UNC paths as absolute.
- `whm:doctor` has a new `API` check before `Auth`. Timeouts, HTTP errors and non-JSON answers fail `API`; only a rejected token or a missing privilege fails `Auth`.
- `Resellers::setLimits()` can turn account and resource limits off again with `limitAccounts: false` and `limitResources: false`.
- `BatchResults::failures()`, `successful()` and `throw()` report a `uapi_cpanel` or `cpanel` command whose cPanel function failed as `UapiCallFailed`, as a single call does. The check now lives in `UapiCallFailed::fromInnerCall()`.
- `Domains::owner()` throws `WhmPermissionDenied` instead of returning null when the token lacks the privilege.
- `composer.json` requires `ext-openssl`, which `whm:doctor`'s TLS check and HTTPS calls need.
- `WhmRequestFailed` (and the request log's warning) fires once, for the error the caller gets, instead of for every failed attempt of a read that a retry then made succeed.
- A batch WHM marks failed because one command failed, but that still answers every command, is a response: `WhmResponseReceived` fires instead of `WhmRequestFailed`, and the request log no longer warns about it. `Whm::call('batch', …)` returns it instead of throwing.
- `whm:doctor` makes one attempt per check, so a server that only answers on retry is reported, and the latency figure no longer includes a failed attempt and its back-off.
- `whm:install` updates and checks the `main` connection, which the `WHM_*` keys it writes belong to, instead of whichever connection is the default; it warns when they differ.
- `whm:call --json` prints only JSON on stdout; the "typed on the command line" warning goes to stderr.
- The `Whm` facade documents `getFake()` and `dispatch()`, and a test keeps its `@method` lines in step with the manager and the client contract.
- A null inside a list parameter no longer leaves a gap in WHM's repeated parameters (`name`, `name-1`, …), which could hide the values after it.
- `whm:doctor --all --json` always prints a list, also when only one connection is configured.
- `whm:install` replaces existing `.env` lines written as `export KEY=…` or `KEY = …` instead of appending a second line that the first one overrides.

## [0.3.0] - 2026-10-08

### Added
- `whm:usage`: accounts at or above `--threshold` percent of their disk or bandwidth limit, highest first; exits 1 when any is (`--all`, `--json`, `--fresh`).
- `Whm::usage()->all()` and `nearLimit()`; `AccountUsage::highestPercent()` and `domain()`.
- `Whm::batch()`: several WHM functions in one request, with `BatchResults` (`successful()`, `failures()`, `throw()`, indexing, iteration). `Whm::fake()` answers batches command by command.
- `Whm::asUser($user)->email()`: mailboxes (list, find, create, password, quota, suspend, delete) and forwarders, with `EmailAccount`, `EmailForwarder` and `CreatedEmailAccount`.
- `Whm::asUser($user)->mysql()`: databases, users and grants, with `MysqlDatabase`, `MysqlUser` and `CreatedMysqlUser`.
- `Whm::transfers()`: move accounts from another server (`createSession()`, `enqueueAccount()`, `start()`, `state()`, `pause()`, `abort()`, `migrate()`), with `RemoteServer`, `TransferOptions` and the `TransferState` enum.

### Changed
- Secret parameters inside query strings, such as a batch command, are redacted.
- Permission failures are classified by `WhmCommandFailed::classify()`, shared by single calls and batches.
- The `WhmClient` contract gains `batch()` and `transfers()`.

## [0.2.0] - 2026-10-08

### Added
- `CreatedAccount::$password`: the password the account was created with. Without one, `create()` generates a strong 24-character password, since WHM does not return a password it generates.
- `AccountModified` event from `Accounts::modify()`, with the changes redacted.
- `WhmClient::withoutRetries()`.

### Changed
- `create()` without a password sends a generated one instead of leaving it to WHM.
- `whm:call` asks for a secret given as a bare key (`password`) with hidden input, and warns when one is typed inline.
- `WhmCheck` names the failure (`Token rejected`, `Missing privilege`, `Unreachable`, `HTTP <status>`, `Not configured`, `WHM error`) and times a single attempt.

### Fixed
- `whm:record` refuses functions missing from cPanel's spec.

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
