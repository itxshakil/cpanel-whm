# Commands

## whm:install

Asks for the host, user and token, then:

1. writes `WHM_HOST`, `WHM_USER`, `WHM_TOKEN` and `WHM_VERIFY_TLS` to `.env` (existing keys are replaced, the rest of the file is kept),
2. publishes `config/cpanel-whm.php` if it doesn't exist yet,
3. runs `whm:doctor` (skip with `--no-doctor`).

## whm:doctor (alias whm:test)

Checks a connection in order and stops at the first failure:

| Check | Passes when | Typical fix on failure |
| --- | --- | --- |
| Config | host and token are set, the port isn't a cPanel port | set `WHM_HOST` / `WHM_TOKEN` |
| DNS | the host resolves (skipped for an IP) | fix the hostname |
| Network | a TCP connection to the port opens | open the port in the server firewall; check cPHulk |
| TLS | the certificate is trusted (warns when it expires within 14 days, or when verification is off) | install a valid certificate, or `WHM_VERIFY_TLS=false` for an IP |
| Auth | `version` succeeds with the token | create a new token; check `WHM_USER` |
| Privileges | the token has `all`, or every `doctor.required_privileges` entry | grant them in the token's ACL |
| Functions | every `doctor.required_functions` entry is in `applist` | update cPanel & WHM |
| Version | the server is at least `doctor.minimum_version` (warning only) | update the server |
| Latency | the `version` call took less than half the timeout (warning only) | raise `WHM_TIMEOUT` |

Options: `--connection=name`, `--all` (every connection), `--json` (for CI). The exit code is 1 when any check fails; warnings don't fail it. Under `Whm::fake()` the network checks are skipped.

In code: `Whm::ping()` / `Whm::ping('ca-1')` returns a `ConnectionReport` (`passed()`, `firstFailure()`, `check('TLS')`, `toArray()`).

## whm:call

```bash
php artisan whm:call listaccts searchtype=domain search=acme
php artisan whm:call passwd user=acme password=secret --post
php artisan whm:call listips --json
php artisan whm:call createacct username=acme domain=acme.example --dry-run
```

Rows are printed as a table when the answer holds one list (like `acct` or `pkg`), otherwise as key/value pairs. Output is redacted. `--dry-run` prints the request with secrets masked and sends nothing. Destructive functions (`removeacct`, `terminatereseller`, `killdns`, `killpkg`, `removezonerecord`, `mass_edit_dns_zone`, `backup_destination_delete`, `api_token_revoke`) ask you to type the function name unless you pass `--force`.

## whm:functions

Without options, lists the functions that have a typed method. With `--server`, lists every function the server offers (`applist`) and the method to use; `--missing` shows only those without a typed method. A search argument filters by name.

## whm:accounts, whm:account, whm:login

```bash
php artisan whm:accounts --search=acme --by=domain
php artisan whm:accounts --package=starter --suspended
php artisan whm:account acme
php artisan whm:login acme --app=Email_Accounts
php artisan whm:login info@acme.example --service=webmail
```

## about

`php artisan about` shows a cPanel WHM section: the default connection, its host, whether TLS is verified, and how many connections are configured.
