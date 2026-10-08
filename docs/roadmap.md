# Roadmap

## Where 0.2 stands

| | WHM API 1 | UAPI |
| --- | --- | --- |
| Typed method (generated) | 621 of 630 (98.6%) | 680 of 693 (98.1%) |
| Hand-written, typed results | 68 functions | through `asUser()` |
| Callable at all | every function, with `Whm::call()` | every function, with `uapi()` |

The functions without a typed method take a JSON body or a file upload; see [coverage](coverage.md).

## Next

- Typed result objects for more generated functions, starting with the ones people ask for.
- `Whm::batch()` for WHM's `batch` function.
- Account transfers (`Transfers` group) as a hand-written module.
- Email and database helpers on `asUser()` with typed results.
- A `whm:usage` command (accounts near their disk or bandwidth limit).

## Before 1.0

- Consistent return types: some write methods return a typed result, others the raw `WhmResponse`, and `Accounts::modify()` fires no event yet.
- `whm:call` prompts for secret parameters instead of taking them as arguments, which shell history and `ps` can see.
- `whm:record` refuses functions that aren't in the catalog, not only those it knows change something.
- `WhmCheck` reports token and privilege failures as such, not as "unreachable", and times one attempt, not the retries.
- `CreatedAccount` exposes the password WHM generated when none was given.

Ideas and function requests are welcome as GitHub issues.
