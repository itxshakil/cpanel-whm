# Accounts, suspensions, packages and sessions

All examples use the facade. The same methods exist on an injected `Itxshakil\CpanelWhm\Contracts\WhmClient` and on `Whm::connection('name')`.

## What methods return

Every module follows the same rule:

- Reads return typed objects or collections (`Account`, `Collection<Package>`, `DnsZone`, ...).
- Creates return a typed object for what was created: `CreatedAccount`, `CreatedToken`, `LoginSession`, `InstalledCertificate`.
- Changes that produce a value you need return that value: the new zone serial from DNS edits, the name WHM saved a package under, the pkgacct session id, the AutoSSL process id.
- Every other change returns WHM's `WhmResponse`, so you can read `warnings()`: WHM can report success while refusing part of a request.

Failures always throw a `WhmException`; no method signals failure through its return value.

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
    password: null,                  // a strong one is generated
    contactEmail: 'ops@acme.example',
    dedicatedIp: false,
    extra: ['owner' => 'reseller1'], // any other createacct parameter
));
```

`CreatedAccount` has `username` (the one WHM assigned: it can differ from the one you asked for), `domain`, `ip`, `package`, `nameservers`, `rawOutput` (WHM's creation log), the full `response` and `password`. Store `username`, not your input.

Without a password (in `password:` or `extra`), a 24-character one with lowercase, uppercase, digits and symbols is generated, because WHM does not return a password it generates itself. `$created->password` is the password the account was created with, given or generated. It is masked when the object is dumped, and the `AccountCreated` event's copy has none.

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

`list()` returns `Collection<Package>` with `name`, `diskQuota`, `bandwidthLimit`, `featureList`, `maxAddonDomains`, `maxSubdomains`, `maxEmailAccounts`, `maxDatabases`, `maxFtpAccounts`, `dedicatedIp` and `raw`. Limits are strings as WHM sends them: a number or `unlimited`. `find(string $name)` returns one or null, and `info(string $name)` reads one package's full settings with `getpkginfo`.

```php
use Itxshakil\CpanelWhm\Data\PackageDefinition;

$name = Whm::packages()->create(new PackageDefinition(
    name: 'starter',
    diskQuotaMb: 10_240,
    bandwidthMb: PackageDefinition::UNLIMITED,
    maxEmailAccounts: 10,
    maxEmailsPerHour: 200,
    featureList: 'default',
    shell: false,
));                                                   // a reseller's package comes back as "reseller_starter"

Whm::packages()->update(new PackageDefinition($name, maxEmailAccounts: 25));   // only non-null settings are sent
Whm::packages()->delete($name);                                                // WHM refuses while an account uses it
```

Anything `PackageDefinition` doesn't name goes in `extra: ['digestauth' => 1]`.

## Quotas

`setDiskQuota(string $user, ?int $megabytes)` and `setBandwidthLimit(string $user, ?int $megabytes)`. `null` means unlimited.

## Sessions

```php
$session = Whm::sessions()->create('acme', SessionService::Cpanel, app: 'FileManager_Home', locale: 'fr');
```

Services: `Cpanel` (`cpaneld`), `Whm` (`whostmgrd`) and `Webmail` (`webmaild`, where the user is an email address). The URL works once; the session ends after 15 minutes without activity. Don't log it: `print_r($session)` masks it for you.

## Server

`version()`, `functions()` (from `applist`), `privileges()` and `hasPrivilege('create-acct')` (from `myprivs`; `all` means root), and `hostname()`.

```php
Whm::server()->loadAverage();                      // LoadAverage: one, five, fifteen
Whm::server()->services()->filter->isDown();       // ServiceStatus: enabled but not running
Whm::server()->restartService('exim', queue: true);
Whm::server()->partitions();                       // DiskPartition: mount, totalBytes, usedBytes, percentUsed
```

## DNS

```php
use Itxshakil\CpanelWhm\Data\DnsRecord;

Whm::dns()->zones();                                   // Collection<string>
$zone = Whm::dns()->zone('acme.example');              // DnsZone: domain, serial, records
$zone->first('www', 'A')?->value();                    // "203.0.113.10"
$zone->ofType('MX');
$zone->named('@');                                     // "@", "acme.example" and "acme.example." all match the apex

Whm::dns()->add('acme.example',
    DnsRecord::a('shop', '203.0.113.20'),
    DnsRecord::txt('@', 'v=spf1 include:_spf.example.com ~all'),
    DnsRecord::mx('@', 10, 'mail.acme.example.'),
);
Whm::dns()->update('acme.example', $zone->first('www', 'A')->withData('203.0.113.30'));
Whm::dns()->remove('acme.example', $zone->first('old', 'CNAME'));

Whm::dns()->create('acme.example', '203.0.113.10');   // adddns
Whm::dns()->reset('acme.example');                     // resetzone
Whm::dns()->delete('acme.example');                    // killdns
```

Record changes go through `mass_edit_dns_zone` with the zone's SOA serial. Without one, the module reads the zone first; pass `serial:` to `edit()` to make "edit what I last read" explicit: if someone changed the zone in between, WHM refuses instead of overwriting. Each method returns the new serial and dispatches `DnsZoneChanged`.

Factories: `a`, `aaaa`, `cname`, `mx`, `ns`, `txt` (split into 255-character strings), `srv`, `caa`. Records read from `zone()` carry the `lineIndex` that `update()` and `remove()` need.

## Domains

```php
Whm::domains()->list();                     // Collection<DomainInfo> across the server
Whm::domains()->list('acme');               // one account's domains
Whm::domains()->find('shop.acme.example');  // ?DomainInfo: user, owner, type (main/addon/sub/parked), documentRoot, ipv4, phpVersion
Whm::domains()->owner('acme.example');      // "acme" or null
Whm::domains()->userData('acme.example');   // Apache settings from domainuserdata

Whm::domains()->createSubdomain('blog.acme.example', 'public_html/blog');
Whm::domains()->createAlias('acme', 'acme.store');     // a parked domain on the account's main site
Whm::domains()->delete('blog.acme.example');           // returns "sub", "addon" or "parked"
```

## Usage

```php
$usage = Whm::usage()->account('acme');
$usage->disk?->percentUsed();               // 0-100, null when unlimited
$usage->bandwidth?->usedMegabytes();
$usage->isNearLimit(90);

Whm::usage()->disk();                       // Collection<DiskUsage> for every account (WHM's quota cache)
Whm::usage()->disk(fresh: true);            // bypass the cache; slower
Whm::usage()->bandwidth(month: 9, year: 2026, reseller: 'res1');
Whm::usage()->bandwidthFor('acme');         // ?BandwidthUsage, with byDomain
```

All sizes are in bytes. WHM reports disk in KiB; the DTOs convert.

## Backups and restores

```php
Whm::backups()->dates();                          // ['2026-09-30', '2026-10-01', ...]
Whm::backups()->users('2026-10-01');              // ['acme' => 'active', ...]
$queueId = Whm::backups()->restore('acme', '2026-10-01', databases: true, mail: true);
Whm::backups()->queue()->stateOf('acme');         // pending, active, completed or null

$session = Whm::backups()->backupAccount('acme'); // pkgacct in the background
Whm::backups()->backupStatus($session);           // RUNNING, COMPLETED or FAILED
Whm::backups()->config();                         // the server's backup settings
```

An account's own backups (full backup to its home directory or FTP, restoring files and databases) are UAPI functions: `Whm::asUser('acme')->api()->backup()->fullbackupToHomedir()`.

## Resellers

```php
Whm::resellers()->list();
Whm::resellers()->create('res1', ownAccount: true);
Whm::resellers()->setPrivileges('res1', ['create-acct', 'suspend-acct', 'list-accts']);
Whm::resellers()->saveAcl('basic', ['create-acct', 'list-accts']);
Whm::resellers()->assignAcl('res1', 'basic');
Whm::resellers()->acls();                         // ['basic' => ['create-acct', 'list-accts'], ...]
Whm::resellers()->setLimits('res1', accounts: 25, diskMb: 51_200, bandwidthMb: 512_000);
Whm::resellers()->stats('res1');                  // ResellerStats: disk, bandwidth, owned accounts
Whm::resellers()->accountCounts('res1')->remaining();
Whm::resellers()->suspend('res1', 'Unpaid', lock: true);
Whm::resellers()->terminate('res1');              // deletes every account it owns
```

## SSL

```php
Whm::ssl()->runAutoSsl('acme');                   // after adding a domain
Whm::ssl()->autoSslProblems('acme');              // Collection<AutoSslProblem>: domain, problem, time
Whm::ssl()->install('acme.example', $certificate, $key, $caBundle);   // InstalledCertificate
```

## API tokens

```php
$created = Whm::tokens()->create('billing', ['create-acct', 'suspend-acct'], now()->addYear(), ['203.0.113.0/24']);
$created->token;                     // the secret, shown once: store it now

Whm::tokens()->list();               // Collection<ApiToken>: name, createdAt, expiresAt, privileges, allowedIps
Whm::tokens()->expiringWithin(14);
Whm::tokens()->revoke('old-token');
```

These manage the tokens of the connection's own user. `php artisan whm:token` warns before one expires.

## Everything else: Whm::api()

Every other documented function has a generated, typed method. See [the generated API](api.md).

```php
Whm::api()->ipAddressManagement()->listips();
Whm::api()->dns()->addZoneKey(domain: 'acme.example', algoNum: 13, keyType: 'simple', active: true);
Whm::asUser('acme')->api()->email()->addPop(email: 'info', password: $password, quota: 1024);
```

## Events

The modules dispatch these after WHM confirms the change:

| Event | Properties |
| --- | --- |
| `AccountCreated` | `connection`, `account` (`CreatedAccount`, without its password) |
| `AccountRemoved` | `connection`, `user` |
| `AccountSuspended` | `connection`, `user`, `reason`, `locked` |
| `AccountUnsuspended` | `connection`, `user` |
| `AccountPackageChanged` | `connection`, `user`, `package` |
| `AccountModified` | `connection`, `user`, `changes` (the `modifyacct` parameters, secrets redacted) |
| `AccountPasswordChanged` | `connection`, `user` (never the password) |
| `DnsZoneChanged` | `connection`, `zone`, `serial`, `added`, `edited`, `removed` |

They are a good fit for an audit log:

```php
Event::listen(AccountSuspended::class, fn ($e) => Activity::log("Suspended {$e->user}: {$e->reason}"));
```

The low-level `WhmRequestSending`, `WhmResponseReceived` and `WhmRequestFailed` fire for every call. They carry redacted copies of the request and response: passwords, new API tokens and login URLs are masked, so they are safe for Telescope, queued listeners and logs.

## Retries and caching

Read-only functions (WHM marks about 240 of them, e.g. `listaccts`, `accountsummary`, `version`) are retried after a connection error or an HTTP 5xx, twice by default with a short back-off. A function that changes something is never retried, so an account can't be created twice. See [configuration](configuration.md#retries).

```php
Whm::cache(300)->accounts()->list();        // cached for 5 minutes
Whm::cache(now()->addHour(), 'redis')->packages()->list();
```

`cache()` returns a copy of the client; only read-only calls are cached, and failures never are. `verify_new_username` is never cached, DNS edits read the zone's serial uncached, and `withoutCache()` gives you an uncached copy back. Cache keys include the server and user, not just the connection name.

## Macros

Every module is macroable:

```php
use Itxshakil\CpanelWhm\Modules\Accounts;

Accounts::macro('ownedBy', fn (string $owner) => $this->search($owner, SearchType::Owner, exact: true));
```

## Health checks

With [spatie/laravel-health](https://spatie.be/docs/laravel-health) installed:

```php
use Itxshakil\CpanelWhm\Health\WhmCheck;
use Spatie\Health\Facades\Health;

Health::checks([
    WhmCheck::new()->warnWhenSlowerThan(1500)->failWhenTokenExpiresWithin(7),
    WhmCheck::new()->connection('ca-1'),
]);
```

The check calls `version` once, without retries, so the response time is one request's. On failure the short summary names the cause (`Token rejected`, `Missing privilege`, `Unreachable`, `HTTP 502`, `Not configured` or `WHM error`) and the message carries the exception's hint.
