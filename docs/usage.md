# Accounts, suspensions, packages and sessions

All examples use the facade. The same methods exist on an injected `Itxshakil\CpanelWhm\Contracts\WhmClient` and on `Whm::connection('name')`.

## Accounts

| Method | WHM function | Returns |
| --- | --- | --- |
| `create(NewAccount $account)` | `createacct` (POST, ≥ 120 s timeout) | `CreatedAccount` |
| `list(?Filter $filter = null)` | `listaccts` | `Collection<Account>` |
| `search(string $term, SearchType $by = User, bool $exact = false)` | `listaccts` | `Collection<Account>` |
| `find(string $user)` | `accountsummary` | `?Account` (null when it does not exist) |
| `exists(string $user)` | `accountsummary` | `bool` |
| `isUsernameAvailable(string $username)` | local rules, then `verify_new_username` | `bool` |
| `changePassword(string $user, string $password, bool $syncDatabasePasswords = false)` | `passwd` (POST) | `WhmResponse` |
| `changePackage(string $user, string $package)` | `changepackage` | `WhmResponse` |
| `modify(string $user, array $changes)` | `modifyacct` (POST) | `WhmResponse` |
| `remove(string $user, bool $keepDns = false)` | `removeacct` (POST) | `WhmResponse` |

### Creating

```php
$created = Whm::accounts()->create(new NewAccount(
    username: 'acme',
    domain: 'acme.example',
    package: 'starter',
    password: null,                  // WHM generates one
    contactEmail: 'ops@acme.example',
    dedicatedIp: false,
    extra: ['owner' => 'reseller1'], // any other createacct parameter
));
```

`CreatedAccount` has `username` (the one WHM assigned: it can differ from the one you asked for), `domain`, `ip`, `package`, `nameservers`, `rawOutput` (WHM's creation log) and the full `response`. Store `username`, not your input.

### Account

`Account` has `username`, `domain`, `package`, `ip`, `email` (null when WHM says `*unknown*`), `owner`, `suspended`, `suspendReason`, `diskUsed`, `diskLimit` (as WHM formats them, e.g. `120M`), `createdAt` and `raw` (everything WHM returned).

### Filters

```php
Filter::where('domain', 'contains', 'acme')->andWhere('plan', '=', 'starter');
```

Operators: `contains`, `begins`, `eq` (`=`), `==`, `lt` (`<`), `lt_equal` (`<=`), `gt` (`>`), `gt_equal` (`>=`), `lt_handle_unlimited`, `gt_handle_unlimited`. Filters work on any list function: `Whm::call('listpkgs', Filter::where(...)->toParams())`.

## Suspensions

`suspend(string $user, string $reason = '', bool $lock = false)`, `unsuspend(string $user)` and `list()` (`Collection<SuspendedAccount>`). `lock: true` stops the account's reseller from unsuspending it.

## Packages

`list()` returns `Collection<Package>` with `name`, `diskQuota`, `bandwidthLimit`, `featureList`, `maxAddonDomains`, `maxSubdomains`, `maxEmailAccounts`, `maxDatabases`, `maxFtpAccounts`, `dedicatedIp` and `raw`. Limits are strings as WHM sends them: a number or `unlimited`. `find(string $name)` returns one or null.

## Quotas

`setDiskQuota(string $user, ?int $megabytes)` and `setBandwidthLimit(string $user, ?int $megabytes)`. `null` means unlimited.

## Sessions

```php
$session = Whm::sessions()->create('acme', SessionService::Cpanel, app: 'FileManager_Home', locale: 'fr');
```

Services: `Cpanel` (`cpaneld`), `Whm` (`whostmgrd`) and `Webmail` (`webmaild`, where the user is an email address). The URL works once; the session ends after 15 minutes without activity. Don't log it: `print_r($session)` masks it for you.

## Server

`version()`, `functions()` (from `applist`), `privileges()` and `hasPrivilege('create-acct')` (from `myprivs`; `all` means root), and `hostname()`.

## Macros

Every module is macroable:

```php
use Itxshakil\CpanelWhm\Modules\Accounts;

Accounts::macro('ownedBy', fn (string $owner) => $this->search($owner, SearchType::Owner, exact: true));
```
