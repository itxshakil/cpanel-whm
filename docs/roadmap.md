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

- Run in production for six weeks with no breaking changes needed.
