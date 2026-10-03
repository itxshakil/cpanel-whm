# Contributing

Thanks for helping. Issues and pull requests are welcome. Please follow the [Code of Conduct](CODE_OF_CONDUCT.md).

## Setup

```bash
git clone https://github.com/itxshakil/cpanel-whm.git
cd cpanel-whm
composer install
```

You don't need a WHM server: every test fakes the network.

## Before opening a PR

```bash
composer test        # PHPUnit
composer analyse     # PHPStan, level max
composer format      # Pint (composer lint checks without fixing)
composer qa          # lint + analyse + test
```

All of these run in CI against PHP 8.3–8.5 and Laravel 12 and 13.

## Ground rules

- **Secrets never leave the Authorization header.** Passwords go in POST bodies; anything logged, printed, dumped or put in an exception goes through `Redactor`. A test should prove it for any new path.
- **A new typed method needs a test that asserts the exact parameters sent to WHM**, and a link to the function's cPanel docs page in its docblock.
- **Read WHM output tolerantly.** WHM sends numbers as strings and booleans as 0/1. Use `Data\Value`, never a bare cast.
- **Faked and real calls share one path.** Anything that interprets a response belongs in the client or a module, never in the transport.
- **No new runtime dependencies** without discussing it in an issue first.

## Commit messages and changelog

Write commit messages in the imperative mood ("Add listzones to the DNS module"), and add a line under "Unreleased" in `CHANGELOG.md`.
