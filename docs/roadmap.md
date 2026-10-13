# Roadmap

## Where 0.3 stands

| | WHM API 1 | UAPI |
| --- | --- | --- |
| Typed method (generated) | 621 of 630 (98.6%) | 680 of 693 (98.1%) |
| Hand-written, typed results | 74 functions | through `asUser()` |
| Callable at all | every function, with `Whm::call()` | every function, with `uapi()` |

The functions without a typed method take a JSON body or a file upload; see [coverage](coverage.md).

## Next

- Typed result objects for more generated functions, starting with the ones people ask for.

## Before 1.0

- Run in production for six weeks with no breaking changes needed.

That is the only criterion. Functions that take a JSON body or a file upload don't block 1.0; they stay callable with `Whm::call()`. What 1.0 promises is in the README, under "Stability and versioning"; the release steps are in RELEASING.md.
