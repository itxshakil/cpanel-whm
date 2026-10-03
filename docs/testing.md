# Testing

`Whm::fake()` replaces the transport for every connection. Your code, the modules and the response parsing all run for real; only the network is replaced. So a faked failure throws exactly the exception a real one would.

```php
$whm = Whm::fake([
    'createacct' => Whm::response(['user' => 'acme', 'ip' => '203.0.113.20']),
    'suspendacct' => Whm::failure('Account is already suspended'),
    'version' => ['version' => '11.134.0.5'],   // a plain array is a successful data payload
]);
```

You don't need any WHM config in tests: under the fake, missing connections get a placeholder.

## Responses

| Helper | Gives |
| --- | --- |
| `Whm::response(array $data = [], string $reason = 'OK', array $metadata = [])` | success (`result = 1`); pass `['output' => ['raw' => '...', 'warnings' => [...]]]` as metadata to test logs and warnings |
| `Whm::failure(string $reason, array $data = [])` | WHM failure (`result = 0`) → `WhmCommandFailed` (or `WhmPermissionDenied` for "Access denied") |
| `Whm::httpError(int $status = 500)` | HTTP error; 401/403 → `WhmAuthenticationFailed` |
| `Whm::connectionError(string $message)` | `WhmConnectionFailed` |
| `Whm::uapi(mixed $data, array $warnings = [], array $messages = [])` | successful `uapi_cpanel` |
| `Whm::uapiFailure(string\|array $errors)` | `UapiCallFailed` |
| `Whm::sequence(...$responses)` | one per call, in order; `->whenEmpty($response)` to keep answering |
| a closure `fn (WhmRequest $request) => ...` | computed per call |

Key `'*'` answers any function. Calls with no answer throw `StrayWhmCall`; `->allowStrayCalls()` answers them with an empty success instead.

## Assertions

```php
$whm->assertCalled('createacct');
$whm->assertCalled('createacct', fn (array $params, WhmRequest $request) => $params['username'] === 'acme' && $request->method === HttpMethod::Post);
$whm->assertNotCalled('removeacct');
$whm->assertCalledTimes('suspendacct', 1);
$whm->assertSentCount(3);
$whm->assertNothingSent();
$whm->recorded('createacct');   // list<WhmRequest>
```

## Http::fake() also works

The real transport uses Laravel's HTTP client, so `Http::fake()` and `Http::preventStrayRequests()` work too. That is how the package tests its own transport. Prefer `Whm::fake()` in app tests: it is shorter and doesn't depend on URLs.

## whm:doctor in tests

Under `Whm::fake()` the DNS, network and TLS checks are skipped. To test them, bind your own `Itxshakil\CpanelWhm\Doctor\NetworkProbe`.
