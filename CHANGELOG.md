# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.5.0] - 2026-10-14

Check these when upgrading:

- `accounts()->create()` refuses more usernames before calling WHM: names ending in "assword" and cPanel's documented reserved aliases, such as `info`, `support`, `sales` and `www`.
- `BatchResults::throw()` throws when WHM answered fewer commands than the batch sent, even if none of the answers failed.
- `Resellers::setLimits()` reads the current state (`acctcounts`, `resellerstats`) when a call leaves a kind of limit out, so the token needs those functions too.
- `Domains::find()`, `Domains::list($user)`, `Usage::diskFor()` and `asUser()->email()->find()` send filter parameters. Tests that assert their exact parameters under `Whm::fake()` need updating.

### Added
- `asUser()->email()->list()`, `mysql()->databases()` and `mysql()->users()` take `page:` and `perPage:` (UAPI pagination, default 100 per page).
- `WhmFunctionSucceeded`: fired after every successful call that changes something, whichever path made it (a module, `Whm::api()`, `Whm::call()`, `uapi()` or each command of a batch), with the connection, function, redacted parameters and response, and `uapi()` for the UAPI function a `uapi_cpanel` call ran. Reads and failures never fire it. The module events (`AccountCreated`, ...) still fire as before, after it.

### Changed
- `Domains::find()`, `Domains::list($user)` and `Usage::diskFor()` (and so `Usage::account()`) ask WHM to filter `get_domain_info` and `get_disk_usage` (`api.filter`), so a single lookup gets one row instead of the whole server's list. `asUser()->email()->find()` filters by address on the UAPI side the same way. Results still match exactly in PHP.
- `UsernameRules` refuses names ending in "assword" and every reserved alias cPanel documents (87 names, up from 35, such as `dovecot`, `mailnull`, `wheel`, `support` and `www`), so `accounts()->create()` stops them before calling WHM.
- `composer.json` requires `guzzlehttp/guzzle` (`^7.8.2|^8.0`, the same range Laravel allows). `HttpTransport` catches Guzzle's exceptions to redact the token and query string from raw errors, which used to depend on Guzzle arriving through `laravel/framework`.
- CI gives its jobs a read-only token, and pins every action outside `actions/` to a commit SHA. Dependabot auto-merges only minor and patch updates of them.
- CI and the Release workflow install the `git archive` Packagist ships (`bin/check-archive`) and fail when `.gitattributes` leaves out a runtime file, a class doesn't autoload or the config doesn't load.

### Fixed
- `Resellers::setLimits()` keeps the kind of limit a call doesn't mention. `setresellerlimits` defaults `enable_account_limit` and `enable_resource_limits` to 0, so setting only the disk quota could switch the account limit off (and the other way round). The method now reads the current state (`acctcounts`, `resellerstats`) and sends that flag back.
- Under Octane, `Whm::` inside a task (`Octane::concurrently()`) or a tick uses that operation's sandbox, as it already did for requests, and the manager lets go of each sandbox when the operation ends. Before, tasks and ticks could reach the previous request's flushed sandbox.
- `BatchResults::throw()` throws `WhmCommandFailed` when WHM answered fewer commands than the batch sent, even if none of the answers failed. It used to return normally, though `complete()` and `successful()` were already false.

## [0.4.0] - 2026-10-14

This release changes some public behaviour, as minor versions may before 1.0.
Check these when upgrading:

- PHP needs `ext-openssl`, and Laravel must be 12.69+ or 13.30+.
- A nested array, a date or any other object passed as a parameter throws `InvalidArgumentException` instead of being sent.
- `Passwords::generate()` refuses lengths below 12, and `Quotas::setDiskQuota()` and `setBandwidthLimit()` refuse 0 and negative values.
- `Domains::owner()` throws `WhmPermissionDenied` instead of returning null when the token lacks the privilege.
- `whm:suspend` and `whm:unsuspend` ask first and refuse without a terminal; pass `--force` in scripts.
- `whm:doctor` fails a plain `http://` connection unless the connection sets `allow_insecure_http`.
- The `WhmClient` contract gains `timeout()`, `withHttpMethod()`, `flushCache()` and `featureLists()`, and `NetworkProbe::certificate()` takes an optional `$caBundle`: custom implementations need them.

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
- `Accounts::create()` takes an optional `$timeout`; 120 seconds stays the default but is no longer a floor, so a queued job can stay below its `retry_after`.
- `NewAccount::asGiven()`: `accounts()->create()` sends the account exactly as given, skipping the local username rules, domain lowercasing and password generation, while still returning a typed `CreatedAccount`.
- `WhmConnectionFailed` keeps a `TransportFailure` as its previous exception (the HTTP client's exception class, the cURL error number as the code, and the sanitised message), and has `curlError()`. The original exception is still not chained, since it carries the URL and the Authorization header.
- `WhmHttpError::body()`: the start of the response body (up to 2,000 characters), with secrets masked, for error pages and JSON errors.
- `Whm::fake()` accepts `uapi:Module::function` keys to answer one UAPI function, falling back to `uapi_cpanel` and `*`; `assertCalled()`, `assertNotCalled()`, `assertCalledTimes()` and `recorded()` take the same keys.
- `log_responses` (`WHM_LOG_RESPONSES`): the request log also records WHM's answer, redacted. `log_levels` sets the level for successes, command failures and HTTP or connection errors (defaults unchanged: info, warning, warning).
- `Backups::activateQueue()` starts the restore queue on its own.
- An optional `timezone` per connection (`WHM_TIMEZONE`). Date objects passed to `backups()->restore()` and `backups()->users()` are converted to it before the day is taken, so a UTC moment no longer asks for the wrong day's backup. Unknown timezones are rejected as `InvalidConfiguration`. Without it, nothing changes.
- `Suspensions::ensureSuspended()` and `ensureUnsuspended()`, and `Packages::deleteIfExists()`, for jobs that may be retried. They read the current state first, act only when it differs, and return whether they changed anything.
- `Whm::withHttpMethod(HttpMethod $method)` returns a client that sends every call with that method, for proxies and WAF rules. Calls carrying a secret stay POST. The README explains how GET or POST is chosen.
- `Whm::dns()->nameservers()` (get_nameserver_config) and `Whm::server()->accountLimits()` (get_current_users_count and get_maximum_users), returning `AccountLimits` with `remaining()` and `isFull()`.
- `Whm::asUser($user)->subdomains()` (list, add, delete, with `Subdomain` objects) and `->backups()` (the account's backup dates, and a full backup into its home directory).

### Changed
- Parameters: backed enums are sent as their value and `Stringable` objects as their string. A nested array, a date or any other object throws `InvalidArgumentException` before anything is sent, instead of reaching WHM as `name[key]=…` and being ignored.
- A successful call that changes something drops that server's cached reads, so `Whm::cache()` never returns data from before your own change. Cache keys gained a generation segment.
- The `WhmClient` contract gains `flushCache()`.
- `NetworkProbe::certificate()` takes an optional `$caBundle`, and `CertificateInfo` gains `publicKeyPin`. Custom probes need the new parameter.
- The Release workflow runs the full CI (lint, PHPStan, Composer validation and the test matrix) on the tagged commit before validating the install.
- Cache keys sort the parameters, so the same read with its parameters in another order hits the same cache entry.
- HTTP error messages take WHM's reason from `data.reason` when `metadata.reason` is empty, as `WhmResponse::reason()` does.
- Docs: the README has a "Retries" section naming the default (two retries for read-only calls), the worst-case wait, `WHM_RETRY_TIMES=0` and `withoutRetries()`.
- `whm:suspend` and `whm:unsuspend` both ask first, with "no" as the default, and refuse without a terminal unless `--force` is given. `whm:unsuspend` gains `--force`.
- Docs: coverage claims say that functions taking a JSON body or a file upload may not be callable by name, `coverage.md` points UAPI functions at `asUser()->uapi()`, and the DNS examples check `first()` for null.
- `Whm::fake()` answers a batch with a failed command the way WHM does: the batch itself has `result = 0` and still carries every command's answer. A `connectionError()` stub for one command becomes a failed command instead of failing the whole batch.
- The README states what the public API is and how versions treat it: contracts may gain methods in a minor release, generated methods need named arguments, and removals from cPanel's spec are deprecated first. It also states the supported cPanel & WHM versions (developed against 11.138, warned below 11.110).
- `Support\Params`, `Support\CuratedFunctions` and `Transport\HttpTransport` are marked `@internal`.
- RELEASING.md aligns the 1.0 criterion with the roadmap and signs release tags; SECURITY.md says which versions get fixes.
- The docs and the Boost guideline say which method fires each account and DNS event, that `Whm::api()`, `Whm::call()` and batches fire none of them, and how to use `WhmResponseReceived` for those paths.
- `whm:doctor` (and `Whm::ping()`) fails a plain `http://` connection, since every call sends the token unencrypted. Set `allow_insecure_http => true` on the connection to accept it with a warning. The Config line says `TLS n/a (http)` for such connections.
- Requires Laravel 12.69+ or 13.30+, the lowest versions CI can install (Composer skips releases with security advisories), so every allowed version is tested.
- Release drafts take the version and the section from the PR title's Conventional Commit type (`feat:`, `fix:`, `docs:`, ...), via `type: …` labels set automatically, instead of from area labels. `skip-changelog` now actually excludes a PR.
- CI also runs the test suite on Windows (PHP 8.5, Laravel 13, highest dependencies).
- CI runs PHPStan on PHP 8.5 as well as 8.4, since some errors only show against the newest PHP's function signatures.

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
- DNS updates and removals send the serial of the `zone()` read their records came from (`DnsRecord::$zoneSerial`), instead of the current serial, so an edit made from a stale read is refused rather than changing the wrong line. Records from different reads can't be edited together.
- `Transfers::migrate()` aborts the session when enqueueing or starting fails, instead of leaving it half-queued on WHM; a start that timed out after reaching WHM is left running.
- `DnsRecord::txt()` splits long text into strings of at most 255 bytes, the DNS limit, instead of 255 characters, and never cuts a multibyte character in half.
- `Ssl::install()` throws `WhmCommandFailed` with `statusmsg` when `installssl` reports `data.status = 0`, instead of returning a normal-looking result.
- Job status polls (`get_pkgacct_session_state`, `get_transfer_session_state`, `restore_queue_state`) are never cached, so `backupStatus()`, `transfers()->state()` and `backups()->queue()` on a `Whm::cache()` client see the job finish.
- `Passwords::generate()` throws `InvalidArgumentException` below `Passwords::MIN_LENGTH` (12) instead of quietly returning a 4-character password.
- `AccountUsage::isNearLimit()` (and `Usage::nearLimit()`, `whm:usage`) never reports an account whose disk and bandwidth are both unlimited, even at a threshold of 0 or below.
- `whm:record` refuses a `--name` with a path in it and an absolute or `..` `--path`, before calling WHM, so a fixture can't overwrite files outside the fixtures directory.
- `UserEmail::list()` asks UAPI for mailbox restrictions (`get_restrictions=1`), so `EmailAccount`'s suspension flags reflect the server instead of always being false.
- `whm:usage` formats sizes itself instead of with `Number::fileSize()`, which needs ext-intl; it no longer crashes on hosts without it.
- `Tokens::expiringWithin()`, `whm:token` and `WhmCheck::failWhenTokenExpiresWithin()` ignore tokens that have already expired, so one old token left in WHM no longer fails them forever. `whm:token` lists expired tokens as a reminder to revoke them.
- Commands with `--json` (`whm:packages`, `whm:usage`, `whm:call`, `whm:record`, …) report a WHM failure as JSON (`error`, `hint`, `exception`) instead of a styled block on stdout.
- `Quotas::setDiskQuota()` and `setBandwidthLimit()` refuse 0 and negative values: WHM reads 0 as unlimited, so a 0 meant as "nothing" granted the opposite. `null` still means unlimited.
- `Domains::createAlias()` without `$webVhostDomain` throws a clear `InvalidArgumentException` when the account doesn't exist, instead of sending `create_parked_domain_for_user` without its required `web_vhost_domain`.
- `UserMysql::grant()` refuses an empty privilege list (blank entries are ignored) instead of sending `privileges=''`; use `revoke()` to take privileges away.
- `extra:` on the generated WHM and UAPI methods, and `$options` on `Backups::backupAccount()`, can add parameters but no longer replace a typed argument the caller gave (or the normalised username).
- `createacct` (120 s) and `terminatereseller` (600 s) get their minimum timeouts on every path, including `Whm::call()`, `Whm::api()` and `whm:call`, not just through the modules. An explicit timeout still wins (`WhmClient::MINIMUM_TIMEOUTS`).
- Generated methods for parameters the spec documents as JSON (`format: json`), such as `massEditDnsZone(add:, edit:)`, encode arrays to JSON strings, one per item; strings still pass through. Ten parameters across WHM and UAPI are affected.
- The generator records parameters it can't give a typed argument because their PHP name is taken: `bin/generate` warns about them and `docs/coverage.md` lists them (none in spec 11.138.0.10).
- `WhmFake` no longer imports PHPUnit: its assertions use PHPUnit when it is installed and throw `FakeAssertionFailed` otherwise, so a production (`--no-dev`) install analyses cleanly. `phpunit/phpunit` is listed under `suggest`.
- `Backups::restore()` no longer loses the queue id when the task is queued but starting the queue fails: it throws `RestoreNotStarted`, which carries `queueId()`, so a retry starts the waiting task instead of queueing the restore twice.
- Adding or updating a DNS record whose data isn't valid UTF-8 now names the record and its line, and explains that it can be removed by line index and added again, instead of a bare "could not be encoded".
- With a reseller's token, `Packages::find('starter')` finds the package WHM saved as `res1_starter`. An exact name still wins, and root's lookups are unchanged.
- `whm:doctor` works with an IPv6 host such as `https://[2001:db8::1]:2087`: the address is recognised as an IP, and the network and TLS probes use it without brackets. `ConnectionConfig::bareHost()` returns the host in that form.
- `whm:doctor`'s DNS check also looks up AAAA records, so a server with only an IPv6 address no longer fails it.
- Under `Whm::fake()`, a misspelled connection name or a connection production would reject (a cPanel port, for example) now throws `InvalidConfiguration`, as in production. The placeholder connection is used only when no connections are configured or the connection's host or token is unset.
- `whm:install` warns when the configuration is cached: the doctor checks the new values, but the app keeps the cached ones until `php artisan config:clear`.
- `whm:doctor` no longer skips checks silently when a `doctor` setting has the wrong type. A `minimum_version` written as a number (`11.110`, which PHP reads as 11.11) and lists that aren't lists produce a warning naming the setting. Comma-separated strings are accepted as lists.
- `whm:doctor`'s TLS check shows when an untrusted certificate expired (or until when it's valid), also with verification off. A timeout or refused connection during the handshake is reported as a handshake failure, not as an untrusted certificate, and the reported reason is OpenSSL's own ("certificate verify failed", a name mismatch).
- `Whm::ping()` and `whm:doctor` no longer make real DNS lookups and socket connections in tests that call `Http::preventStrayRequests()`: the DNS, network and TLS checks are skipped there, unless a custom `NetworkProbe` is bound.
- `ServiceStatus::isDown()` no longer reports enabled services that WHM doesn't monitor as down. WHM omits `running` for them, so `ServiceStatus::$running` is now `?bool` and null in that case.
- Bandwidth usage leaves out accounts and domains removed during the month, which `showbw` still lists. A user removed and created again no longer risks `bandwidthFor()`, `all()` or `nearLimit()` picking the old row, and a removed domain no longer overwrites a live one in `byDomain`. `bandwidth(includeDeleted: true)` keeps them, flagged with `BandwidthUsage::$deleted`.
- `Resellers::stats()` leaves accounts removed during the month out of `ResellerStats::$accounts` (`filter_deleted=1`); pass `includeDeleted: true` to keep them. The docblock now says the list includes the reseller's own account, as WHM documents.

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
