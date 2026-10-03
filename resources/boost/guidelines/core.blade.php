## cPanel WHM (itxshakil/cpanel-whm)

- Use the `Itxshakil\CpanelWhm\Facades\Whm` facade, or inject `Itxshakil\CpanelWhm\Contracts\WhmClient`. Never call the WHM API with `Http::` directly.
- Prefer the hand-written modules, which return typed objects: `Whm::accounts()`, `suspensions()`, `packages()`, `quotas()`, `sessions()`, `server()`, `dns()`, `domains()`, `usage()`, `backups()`, `resellers()`, `ssl()`, `tokens()`.
- For any other WHM function use the generated API with named arguments: `Whm::api()->dns()->addZoneKey(domain: ..., algoNum: 13, keyType: 'simple')`. For UAPI: `Whm::asUser($user)->api()->email()->addPop(email: ..., password: ...)`. Run `php artisan whm:functions <search>` (add `--uapi` for UAPI) to find the method. `Whm::call('function', [...])` is the last resort.
- Edit DNS with `Whm::dns()->add()/update()/remove()` and `DnsRecord::a()/mx()/txt()`; records to update or remove must come from `Whm::dns()->zone()`.
- Listen to `AccountCreated`, `AccountSuspended`, `DnsZoneChanged` and the other events in `Itxshakil\CpanelWhm\Events` instead of wrapping module calls.
- Use `Whm::cache($seconds)` for read-only calls that pages repeat, such as package lists.
- Store the username from `CreatedAccount::$username`: WHM may rename the account.
- Send passwords with `HttpMethod::Post` when using `Whm::call()`; typed methods already do.
- Catch `Itxshakil\CpanelWhm\Exceptions\WhmException` (or a subclass) around WHM calls; never let failures surface as generic errors. `WhmCommandFailed::reason()` holds WHM's message, `UapiCallFailed::errors()` the UAPI errors.
- In tests, always use `Whm::fake([...])` with `Whm::response()`, `Whm::failure()`, `Whm::uapi()` or `Whm::fixture()` (from `php artisan whm:record`), and assert with `assertCalled()` / `assertNotCalled()`. Unfaked calls throw `StrayWhmCall`.
- Diagnose connection problems with `php artisan whm:doctor`.
