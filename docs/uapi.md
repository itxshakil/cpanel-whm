# UAPI through WHM

cPanel's own API (UAPI) normally needs a cPanel token per account. WHM's `uapi_cpanel` runs any UAPI function as an account using your WHM token instead, and this package uses it:

```php
$result = Whm::asUser('acme')->uapi('Email', 'list_pops', ['regex' => 'info']);

$result->successful;   // true
$result->data;         // the function's data, as documented for that UAPI function
$result->get('0.email');
$result->toArray();
$result->warnings;     // list<string>
$result->messages;
```

Calls are sent as POST, so passwords you pass (for example to `Email::add_pop`) never appear in a URL.

Almost every documented UAPI function (680 of 693) also has a typed method, one accessor per module. The rest take a JSON body or a file upload; see [coverage](coverage.md).

```php
Whm::asUser('acme')->api()->email()->listPops(regex: 'info');
Whm::asUser('acme')->api()->email()->addPop(email: 'info', password: $password, quota: 1024);
```

See [the generated API](api.md).

## Email, MySQL, subdomains and backups, with typed results

The areas most apps need have hand-written helpers on top of the generated methods:

```php
$email = Whm::asUser('acme')->email();

$email->list();                                  // Collection<EmailAccount>, without the main account's system mailbox
$email->find('info@acme.example');               // ?EmailAccount
$created = $email->create('info@acme.example', quotaMegabytes: 1024);
$created->password;                              // generated when you pass none; show or send it once
$email->changePassword('info@acme.example', $password);
$email->setQuota('info@acme.example', null);     // null = unlimited
$email->suspendLogin('info@acme.example');       // and unsuspendLogin, suspendIncoming, unsuspendIncoming
$email->delete('info@acme.example');

$email->forwarders('acme.example');              // Collection<EmailForwarder>
$email->addForwarder('info@acme.example', 'ops@example.com');
$email->deleteForwarder('info@acme.example', 'ops@example.com');

$mysql = Whm::asUser('acme')->mysql();

$mysql->databases();                             // Collection<MysqlDatabase>: name, diskUsageBytes, users
$mysql->users();                                 // Collection<MysqlUser>: name, shortName, databases
$mysql->createDatabase('acme_shop');
$user = $mysql->createUser('acme_app');          // CreatedMysqlUser, with a generated password
$mysql->grant('acme_app', 'acme_shop');          // ALL PRIVILEGES, or ['SELECT', 'INSERT', ...]
$mysql->revoke('acme_app', 'acme_shop');
$mysql->changePassword('acme_app', $password);
$mysql->deleteUser('acme_app');
$mysql->deleteDatabase('acme_shop');

$subdomains = Whm::asUser('acme')->subdomains();

$subdomains->list();                             // Collection<Subdomain>: domain, label, rootDomain, documentRoot
$subdomains->add('shop', 'acme.example', 'public_html/shop');   // document root relative to home; cPanel picks one when omitted
$subdomains->delete('shop.acme.example');

$backups = Whm::asUser('acme')->backups();

$backups->list();                                // Collection<string>: the server's backup dates of this account, as YYYY-MM-DD
$pid = $backups->toHomeDirectory('ops@acme.example');   // starts a full backup in the background; the email hears when it ends
```

- `EmailAccount` has `address`, `user`, `domain`, `usedBytes`, `quotaBytes` (null when unlimited), `percentUsed()`, `loginSuspended`, `incomingSuspended`, `outgoingSuspended`, `outgoingHeld`, `modifiedAt` and `raw`.
- Mailbox addresses are always full (`info@acme.example`). An address without a domain throws `InvalidArgumentException` before anything is sent.
- MySQL names are sent as given. When the server prefixes database names (cPanel's default), they must start with the account's username and an underscore.
- Changes return the `UapiResult`; failures throw `UapiCallFailed` as usual. All four modules are macroable.
- Backups to a remote host (`fullbackup_to_scp_with_key`, `_with_password`, `_ftp`) take credentials and a destination, and are under `Whm::asUser('acme')->api()->backup()`.

## Two levels of failure

`uapi_cpanel` can succeed at the WHM level while the UAPI function inside it fails. The package checks both:

- WHM refused the call (bad user, no privilege): `WhmCommandFailed` / `WhmPermissionDenied`.
- The UAPI function failed (`data.uapi.status = 0`): `UapiCallFailed`, with `errors()`, `module()`, `function()` and the full `result()`.

The same check runs for `Whm::call('uapi_cpanel', ...)` and for WHM's `cpanel` function (`Whm::api()->apiDevelopmentTools()->cpanel(...)`), which reports a failed UAPI or cPanel API 2 function as `UapiCallFailed` too.

```php
try {
    Whm::asUser('acme')->uapi('Email', 'add_pop', ['email' => 'info', 'password' => $password]);
} catch (UapiCallFailed $e) {
    $e->errors();   // ['The account info@acme.example already exists!']
}
```

## Useful modules

`Email`, `Mysql`, `SSL`, `DomainInfo`, `SubDomain`, `Backup`, `Quota`, `ResourceUsage`, `StatsBar`. Function names and parameters are in the [cPanel UAPI docs](https://api.docs.cpanel.net/cpanel/introduction).
