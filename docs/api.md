# The generated API

Every documented WHM API 1 and UAPI function has a typed method, generated from cPanel's OpenAPI documents. [docs/coverage.md](coverage.md) lists the numbers and the few functions without one.

## WHM API 1

`Whm::api()` groups functions the way cPanel's documentation does:

```php
Whm::api()->accounts()->listaccts(searchtype: 'domain', search: 'acme');
Whm::api()->dns()->parseDnsZone(zone: 'acme.example');
Whm::api()->ipAddressManagement()->listips();
Whm::api()->sslCertificates()->fetchSslVhosts();
Whm::api()->loginSecurityCpHulk()->flushCphulkLoginHistoryForIps(ip: ['203.0.113.7', '203.0.113.8']);
Whm::api()->serverAdministration()->restartservice(service: 'httpd');
```

Every method returns the checked `WhmResponse`: a failure throws the same exceptions as everywhere else, and `->get('key.path')` reads the data.

## UAPI

`Whm::asUser($user)->api()` has one accessor per UAPI module. Calls run through WHM's `uapi_cpanel` with your WHM token:

```php
$cpanel = Whm::asUser('acme')->api();

$cpanel->email()->addPop(email: 'info', password: $password, quota: 1024, domain: 'acme.example');
$cpanel->mysql()->createDatabase(name: 'acme_shop');
$cpanel->subDomain()->addsubdomain(domain: 'blog', rootdomain: 'acme.example');
$cpanel->backup()->fullbackupToHomedir(email: 'ops@acme.example');
```

Each returns a `UapiResult`; a UAPI failure throws `UapiCallFailed`.

## How arguments are sent

- **Use named arguments.** Methods follow the spec's parameter names in camelCase (`key_id` → `keyId`, `MAX_EMAIL_PER_HOUR` → `maxEmailPerHour`, `acl-add-pkg` → `aclAddPkg`). Required parameters come first; the order of the rest may change when cPanel updates the spec.
- **Optional arguments you leave out are not sent.**
- **Flags** documented as 0/1 accept `true` / `false`.
- **Lists** become WHM's repeated parameters: `zone: ['a.test', 'b.test']` sends `zone=a.test&zone-1=b.test`.
- **`extra:`** takes anything else, sent as given: wildcard parameters such as `copymysqldb-*`, or a parameter newer than the spec.
- Functions that change something are sent as **POST**, so passwords and keys stay out of URLs and server logs. Read-only ones are GET and can be [retried and cached](usage.md#retries-and-caching). A call that carries a secret (a password, token, passphrase or key) is always POST, read-only or not.

Docblocks carry cPanel's summary, each parameter's description, `@deprecated` where cPanel deprecated a function, and a link to its documentation page.

## Hand-written or generated?

Hand-written modules (`Whm::accounts()`, `Whm::dns()`, `Whm::backups()`, ...) take care of the details: they check usernames, return typed objects, pick the right timeouts, use the DNS serial, and dispatch events. Use them when one exists, and the generated API for everything else. `php artisan whm:functions <search>` shows which to use.

## Regenerating

The generated code is committed, so applications never run the generator. To update it to a newer cPanel release:

```bash
composer generate                 # downloads the specs into build/openapi, regenerates, runs Pint
php bin/generate --refresh        # download the specs again
php bin/generate --whm=whm.openapi.yaml --uapi=cpanel.openapi.yaml
```

It rewrites `src/Api/Whm/*`, `src/Api/Uapi/*`, `src/Api/WhmApi.php`, `src/Api/UapiApi.php`, `src/Support/FunctionCatalog.php` and `docs/coverage.md`. The generator lives in `generator/` and is not shipped in the Composer package.
