# Roadmap and API coverage

WHM API 1 has over 600 functions. Every one is callable today with `Whm::call()`, with full error handling. Typed methods are being added in three tiers:

| Tier | Covers | How | Release |
| --- | --- | --- | --- |
| Curated | The everyday functions: accounts, suspensions, packages (read), quotas, sessions, UAPI bridge, server info | Hand-written, typed results | 0.1 (this release) |
| Curated, part 2 | Packages (write), DNS zones with serial-checked edits, resellers and ACLs, API tokens, AutoSSL | Hand-written | 0.2 |
| Generated WHM | Every other WHM API 1 function in cPanel's OpenAPI spec | `bin/generate` from `whm.openapi.yaml`, committed code, typed parameters, docs links, `@deprecated` flags | 0.3 |
| Generated UAPI | Every UAPI module through `asUser()` | Same generator over `cpanel.openapi.yaml` | 0.4 |

Goal for 1.0: 100% of documented WHM API 1 functions with typed methods, and at least 80% with typed results. A coverage report (`docs/coverage.md`) and README badges will track both numbers.

## Also planned

- `whm:suspend`, `whm:unsuspend`, `whm:packages`, `whm:token` (expiry warning) and `whm:record` (save redacted fixtures) commands.
- Retries for read-only functions and `Whm::cache()` for slow reads.
- A `WhmCheck` for spatie/laravel-health.
- Batch calls (`batch`).
- A Laravel Boost guidelines file is already included in `resources/boost/guidelines`.

Ideas and function requests are welcome as GitHub issues.
