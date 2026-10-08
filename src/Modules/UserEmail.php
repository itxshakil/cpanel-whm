<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Modules;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Itxshakil\CpanelWhm\Data\CreatedEmailAccount;
use Itxshakil\CpanelWhm\Data\EmailAccount;
use Itxshakil\CpanelWhm\Data\EmailForwarder;
use Itxshakil\CpanelWhm\Data\UapiResult;
use Itxshakil\CpanelWhm\Exceptions\UapiCallFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\Support\Passwords;
use SensitiveParameter;

/**
 * Mailboxes and forwarders of one account, through UAPI Email:
 * Whm::asUser('acme')->email()->create('info@acme.example').
 *
 * Addresses are always full ("info@acme.example"): several UAPI functions
 * need the domain, and the account may have more than one.
 */
class UserEmail extends UserModule
{
    /**
     * The account's mailboxes, without the main account's system mailbox.
     *
     * @return Collection<int, EmailAccount>
     *
     * @throws UapiCallFailed
     * @throws WhmException
     */
    public function list(?string $domain = null): Collection
    {
        $rows = self::rows($this->user->api()->email()->listPopsWithDisk(domain: $domain)->data);

        return collect($rows)
            ->reject(static fn (array $row): bool => ($row['email'] ?? null) === 'Main Account')
            ->map(EmailAccount::fromArray(...))
            ->values();
    }

    /**
     * @throws UapiCallFailed
     * @throws WhmException
     */
    public function find(string $address): ?EmailAccount
    {
        [, $domain] = self::split($address);
        $address = mb_strtolower(trim($address));

        return $this->list($domain)->first(static fn (EmailAccount $account): bool => $account->address === $address);
    }

    /**
     * Create a mailbox. Without a password a strong one is generated;
     * CreatedEmailAccount::$password holds the one it was created with.
     *
     * @param  int|null  $quotaMegabytes  null for unlimited
     *
     * @throws UapiCallFailed
     * @throws WhmException
     */
    public function create(string $address, #[SensitiveParameter] ?string $password = null, ?int $quotaMegabytes = null, bool $sendWelcomeEmail = false): CreatedEmailAccount
    {
        [$local, $domain] = self::split($address);
        $password ??= Passwords::generate();

        $result = $this->user->api()->email()->addPop(
            email: $local,
            password: $password,
            domain: $domain,
            quota: $quotaMegabytes ?? 'unlimited',
            sendWelcomeEmail: $sendWelcomeEmail,
        );

        return new CreatedEmailAccount("{$local}@{$domain}", $password, $result);
    }

    /**
     * @throws UapiCallFailed
     * @throws WhmException
     */
    public function changePassword(string $address, #[SensitiveParameter] string $password): UapiResult
    {
        [$local, $domain] = self::split($address);

        return $this->user->api()->email()->passwdPop(email: $local, password: $password, domain: $domain);
    }

    /**
     * @param  int|null  $megabytes  null for unlimited
     *
     * @throws UapiCallFailed
     * @throws WhmException
     */
    public function setQuota(string $address, ?int $megabytes): UapiResult
    {
        [$local, $domain] = self::split($address);

        return $this->user->api()->email()->editPopQuota(domain: $domain, email: $local, quota: $megabytes === null ? 'unlimited' : (string) $megabytes);
    }

    /**
     * Delete a mailbox and its mail. This cannot be undone.
     *
     * @throws UapiCallFailed
     * @throws WhmException
     */
    public function delete(string $address): UapiResult
    {
        [$local, $domain] = self::split($address);

        return $this->user->api()->email()->deletePop(email: $local, domain: $domain);
    }

    /**
     * Stop the mailbox from logging in, sending and reading mail.
     *
     * @throws UapiCallFailed
     * @throws WhmException
     */
    public function suspendLogin(string $address): UapiResult
    {
        return $this->user->api()->email()->suspendLogin(email: self::full($address));
    }

    /**
     * @throws UapiCallFailed
     * @throws WhmException
     */
    public function unsuspendLogin(string $address): UapiResult
    {
        return $this->user->api()->email()->unsuspendLogin(email: self::full($address));
    }

    /**
     * Reject mail sent to the mailbox.
     *
     * @throws UapiCallFailed
     * @throws WhmException
     */
    public function suspendIncoming(string $address): UapiResult
    {
        return $this->user->api()->email()->suspendIncoming(email: self::full($address));
    }

    /**
     * @throws UapiCallFailed
     * @throws WhmException
     */
    public function unsuspendIncoming(string $address): UapiResult
    {
        return $this->user->api()->email()->unsuspendIncoming(email: self::full($address));
    }

    /**
     * @return Collection<int, EmailForwarder>
     *
     * @throws UapiCallFailed
     * @throws WhmException
     */
    public function forwarders(string $domain): Collection
    {
        return collect(self::rows($this->user->api()->email()->listForwarders(domain: $domain)->data))
            ->map(EmailForwarder::fromArray(...))
            ->values();
    }

    /**
     * Forward mail for $address to $destination (any address, local or not).
     *
     * @throws UapiCallFailed
     * @throws WhmException
     */
    public function addForwarder(string $address, string $destination): UapiResult
    {
        [$local, $domain] = self::split($address);

        return $this->user->api()->email()->addForwarder(domain: $domain, email: $local, fwdopt: 'fwd', fwdemail: trim($destination));
    }

    /**
     * @throws UapiCallFailed
     * @throws WhmException
     */
    public function deleteForwarder(string $address, string $destination): UapiResult
    {
        return $this->user->api()->email()->deleteForwarder(address: self::full($address), forwarder: trim($destination));
    }

    /**
     * @return array{string, string} the local part and the domain, lowercased
     *
     * @throws InvalidArgumentException when the address has no domain
     */
    private static function split(string $address): array
    {
        $parts = explode('@', mb_strtolower(trim($address)));

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new InvalidArgumentException("\"{$address}\" is not a full email address. Give the mailbox with its domain, e.g. info@example.com.");
        }

        return [$parts[0], $parts[1]];
    }

    private static function full(string $address): string
    {
        return implode('@', self::split($address));
    }
}
