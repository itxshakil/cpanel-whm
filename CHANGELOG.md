# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

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
