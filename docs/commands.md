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
php artisan whm:call passwd user=acme password        # asks for the password, hidden
php artisan whm:call listips --json
php artisan whm:call createacct username=acme domain=acme.example --dry-run
```

Rows are printed as a table when the answer holds one list (like `acct` or `pkg`), otherwise as key/value pairs. Output is redacted. Give a secret parameter as a bare key (`password`) and the command asks for it with hidden input; one typed as `password=...` works but prints a warning, since shell history and the process list keep it. Calls use the HTTP method cPanel documents for the function (POST for anything that changes the server and for undocumented functions), and any call with a secret parameter is sent as POST; `--post` forces POST. `--dry-run` prints the request with secrets masked and sends nothing. Destructive functions (`removeacct`, `terminatereseller`, `killdns`, `killpkg`, `removezonerecord`, `mass_edit_dns_zone`, `backup_destination_delete`, `api_token_revoke`, `delete_domain`, `resetzone`, `unsetupreseller`, `restore_queue_clear_all_tasks`, `reboot`) ask you to type the function name unless you pass `--force`.

## whm:functions

Finds the method for any function. Searches names and summaries:

```bash
php artisan whm:functions zone             # every WHM function about zones, with its method
php artisan whm:functions --curated        # only hand-written methods with typed results
php artisan whm:functions pop --uapi       # UAPI functions, run with Whm::asUser($user)->api()
php artisan whm:functions --server --missing   # what this server offers that has no typed method (plugins)
```

## whm:suspend, whm:unsuspend

```bash
php artisan whm:suspend acme --reason="Unpaid invoice #1042" --lock
php artisan whm:unsuspend acme
```

`whm:suspend` asks first unless you pass `--force`.

## whm:packages

Lists packages and their limits; `--json` prints WHM's raw settings.

## whm:dns

```bash
php artisan whm:dns                        # every zone
php artisan whm:dns acme.example           # the zone's records, with line numbers and the serial
php artisan whm:dns acme.example --type=MX
```

## whm:token

Lists the connection user's API tokens with their expiry and privileges. Exits with 1 when a token expires within `--days` (14 by default), so it works as a scheduled check:

```php
Schedule::command('whm:token --days=14')->weekly()->emailOutputOnFailure('ops@example.com');
```

## whm:record

Saves a read-only function's redacted response as a fixture for `Whm::fake()`. See [testing](testing.md#record-real-responses). Refuses functions that change the server, and functions missing from cPanel's spec (plugins, for example), since it cannot tell whether they do.

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
