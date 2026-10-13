# Errors

Every exception extends `Itxshakil\CpanelWhm\Exceptions\WhmException` and has `hint(): ?string`, a sentence on what to do next. Messages never contain the token, passwords or query strings.

```php
try {
    Whm::accounts()->create($account);
} catch (InvalidUsername $e) {
    // $e->rule(): "it cannot start with a number."
} catch (WhmCommandFailed $e) {
    // WHM ran createacct and said no: $e->reason(), $e->rawOutput()
} catch (WhmConnectionFailed $e) {
    // $e->mayHaveReachedServer(): WHM stopped answering after the call was sent,
    // so check whether the account exists before creating it again.
    // Accounts::createOrRecover() does that check for you.
} catch (WhmException $e) {
    report($e);
}
```

| Exception | Meaning | Retry? |
| --- | --- | --- |
| `WhmConnectionFailed` | DNS, refused connection, TLS failure or timeout. `mayHaveReachedServer()` is true when the call was sent and WHM stopped answering (a read timeout, an empty reply). `curlError()` gives the cURL error number, and `getPrevious()` is a `TransportFailure` naming the HTTP client's exception class, with the URL's query string and the request left out. | Reads, yes. Writes: only when `mayHaveReachedServer()` is false; otherwise check first. |
| `WhmAuthenticationFailed` (extends `WhmHttpError`) | HTTP 401/403 | No: fix the token. |
| `WhmHttpError` | Other HTTP errors; `notJson` when the host answers with HTML. `status()`, and `body()` with the start of the response (up to 2,000 characters, secrets masked) | Only for 5xx. |
| `WhmCommandFailed` | `metadata.result = 0` | Depends on `reason()`. |
| `WhmPermissionDenied` (extends `WhmCommandFailed`) | The token's ACL doesn't allow the function | No: grant the privilege. |
| `AccountNotFound` (extends `WhmCommandFailed`) | The named cPanel account doesn't exist ("You do not have a user named …", "Account does not exist") | No. In a retried job, "already gone" may count as done. |
| `UapiCallFailed` | The cPanel function failed inside a successful `uapi_cpanel` or `cpanel` call, including raw `Whm::call()` | Depends on `errors()`. |
| `InvalidUsername` | A local username rule failed | No. |
| `InvalidConfiguration` | Missing host/token, unknown connection, cPanel port | No. |
| `StrayWhmCall` | `Whm::fake()` got a call it has no answer for | Test setup. |

## Warnings on success

WHM sometimes returns success while refusing part of a request, for example "You cannot change backup settings." Check `$response->warnings()` when that matters.
