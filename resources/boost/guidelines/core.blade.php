## cPanel WHM (itxshakil/cpanel-whm)

- Use the `Itxshakil\CpanelWhm\Facades\Whm` facade, or inject `Itxshakil\CpanelWhm\Contracts\WhmClient`. Never call the WHM API with `Http::` directly.
- Prefer typed methods: `Whm::accounts()->create(new NewAccount(...))`, `->list()`, `->find()`, `Whm::suspensions()->suspend()`, `Whm::packages()->list()`, `Whm::sessions()->create()`, `Whm::asUser($user)->uapi($module, $function, $params)`. For anything else use `Whm::call('function', [...])`.
- Store the username from `CreatedAccount::$username`: WHM may rename the account.
- Send passwords with `HttpMethod::Post` when using `Whm::call()`; typed methods already do.
- Catch `Itxshakil\CpanelWhm\Exceptions\WhmException` (or a subclass) around WHM calls; never let failures surface as generic errors. `WhmCommandFailed::reason()` holds WHM's message, `UapiCallFailed::errors()` the UAPI errors.
- In tests, always use `Whm::fake([...])` with `Whm::response()`, `Whm::failure()`, `Whm::uapi()`, and assert with `assertCalled()` / `assertNotCalled()`. Unfaked calls throw `StrayWhmCall`.
- Diagnose connection problems with `php artisan whm:doctor`.
