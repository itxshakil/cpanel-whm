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
    // nothing reached WHM: safe to retry later
} catch (WhmException $e) {
    report($e);
}
```

| Exception | Meaning | Retry? |
| --- | --- | --- |
| `WhmConnectionFailed` | DNS, refused connection, TLS failure or timeout | Reads, yes. Writes: check first (a timeout can happen after WHM acted). |
| `WhmAuthenticationFailed` (extends `WhmHttpError`) | HTTP 401/403 | No: fix the token. |
| `WhmHttpError` | Other HTTP errors; `notJson` when the host answers with HTML | Only for 5xx. |
| `WhmCommandFailed` | `metadata.result = 0` | Depends on `reason()`. |
| `WhmPermissionDenied` (extends `WhmCommandFailed`) | The token's ACL doesn't allow the function | No: grant the privilege. |
| `UapiCallFailed` | UAPI status 0 inside a successful `uapi_cpanel` | Depends on `errors()`. |
| `InvalidUsername` | A local username rule failed | No. |
| `InvalidConfiguration` | Missing host/token, unknown connection, cPanel port | No. |
| `StrayWhmCall` | `Whm::fake()` got a call it has no answer for | Test setup. |

## Warnings on success

WHM sometimes returns success while refusing part of a request, for example "You cannot change backup settings." Check `$response->warnings()` when that matters.
