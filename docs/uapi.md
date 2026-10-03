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

Every documented UAPI function also has a typed method, one accessor per module:

```php
Whm::asUser('acme')->api()->email()->listPops(regex: 'info');
Whm::asUser('acme')->api()->email()->addPop(email: 'info', password: $password, quota: 1024);
```

See [the generated API](api.md).

## Two levels of failure

`uapi_cpanel` can succeed at the WHM level while the UAPI function inside it fails. The package checks both:

- WHM refused the call (bad user, no privilege): `WhmCommandFailed` / `WhmPermissionDenied`.
- The UAPI function failed (`data.uapi.status = 0`): `UapiCallFailed`, with `errors()`, `module()`, `function()` and the full `result()`.

```php
try {
    Whm::asUser('acme')->uapi('Email', 'add_pop', ['email' => 'info', 'password' => $password]);
} catch (UapiCallFailed $e) {
    $e->errors();   // ['The account info@acme.example already exists!']
}
```

## Useful modules

`Email`, `Mysql`, `SSL`, `DomainInfo`, `SubDomain`, `Backup`, `Quota`, `ResourceUsage`, `StatsBar`. Function names and parameters are in the [cPanel UAPI docs](https://api.docs.cpanel.net/cpanel/introduction).
