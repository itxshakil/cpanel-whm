<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

use Itxshakil\CpanelWhm\Exceptions\InvalidUsername;
use Itxshakil\CpanelWhm\Support\UsernameRules;
use SensitiveParameter;

/**
 * The input for createacct.
 */
final readonly class NewAccount
{
    /**
     * The limits default to the package's; set one only to override it for this account.
     *
     * @param  string|null  $password  when omitted (here and in $extra), Accounts::create() generates a strong one and returns it
     * @param  array<string, mixed>  $extra  any other createacct parameter, sent as-is
     * @param  string|null  $customIp  a specific IPv4 address for the account (customip)
     * @param  int|string|null  $quotaMb  disk quota in MB, or "unlimited" (quota)
     * @param  int|string|null  $bandwidthMb  monthly bandwidth in MB, or "unlimited" (bwlimit)
     * @param  string|null  $owner  the reseller that owns the account (owner)
     * @param  bool|null  $shell  whether the account gets shell access (hasshell)
     * @param  bool  $asGiven  send exactly what was given: no local username rules, no domain
     *                         lowercasing and no generated password; WHM does its own checks
     */
    public function __construct(
        public string $username,
        public ?string $domain = null,
        public ?string $package = null,
        #[SensitiveParameter]
        public ?string $password = null,
        public ?string $contactEmail = null,
        public bool $dedicatedIp = false,
        public array $extra = [],
        public ?string $customIp = null,
        public int|string|null $quotaMb = null,
        public int|string|null $bandwidthMb = null,
        public ?string $featureList = null,
        public ?string $owner = null,
        public int|string|null $maxEmailsPerHour = null,
        public ?string $language = null,
        public ?bool $shell = null,
        public bool $asGiven = false,
    ) {}

    /**
     * A copy that Accounts::create() sends exactly as given, for apps that
     * choose usernames and passwords themselves and rely on WHM's validation.
     * Without a password, WHM generates one and CreatedAccount::$password is null.
     */
    public function asGiven(): self
    {
        return new self(
            username: $this->username,
            domain: $this->domain,
            package: $this->package,
            password: $this->password,
            contactEmail: $this->contactEmail,
            dedicatedIp: $this->dedicatedIp,
            extra: $this->extra,
            customIp: $this->customIp,
            quotaMb: $this->quotaMb,
            bandwidthMb: $this->bandwidthMb,
            featureList: $this->featureList,
            owner: $this->owner,
            maxEmailsPerHour: $this->maxEmailsPerHour,
            language: $this->language,
            shell: $this->shell,
            asGiven: true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'username' => $this->username,
            'domain' => $this->domain,
            'package' => $this->package,
            'password' => $this->password === null ? null : '[REDACTED]',
            'contactEmail' => $this->contactEmail,
            'dedicatedIp' => $this->dedicatedIp,
            'customIp' => $this->customIp,
            'quotaMb' => $this->quotaMb,
            'bandwidthMb' => $this->bandwidthMb,
            'featureList' => $this->featureList,
            'owner' => $this->owner,
            'maxEmailsPerHour' => $this->maxEmailsPerHour,
            'language' => $this->language,
            'shell' => $this->shell,
            'asGiven' => $this->asGiven,
        ];
    }

    /**
     * The password to send: the one given here or in $extra, or null.
     */
    public function givenPassword(): ?string
    {
        $fromExtra = $this->extra['password'] ?? null;

        return $this->password ?? (is_string($fromExtra) && $fromExtra !== '' ? $fromExtra : null);
    }

    public function withPassword(#[SensitiveParameter] string $password): self
    {
        return new self(
            username: $this->username,
            domain: $this->domain,
            package: $this->package,
            password: $password,
            contactEmail: $this->contactEmail,
            dedicatedIp: $this->dedicatedIp,
            extra: $this->extra,
            customIp: $this->customIp,
            quotaMb: $this->quotaMb,
            bandwidthMb: $this->bandwidthMb,
            featureList: $this->featureList,
            owner: $this->owner,
            maxEmailsPerHour: $this->maxEmailsPerHour,
            language: $this->language,
            shell: $this->shell,
            asGiven: $this->asGiven,
        );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidUsername
     */
    public function toParams(): array
    {
        $params = [
            'username' => $this->asGiven ? $this->username : UsernameRules::assertValid($this->username),
            'domain' => $this->domain === null || $this->asGiven ? $this->domain : mb_strtolower(trim($this->domain)),
            'plan' => $this->package,
            'password' => $this->password,
            'contactemail' => $this->contactEmail,
            'ip' => $this->dedicatedIp ? 'y' : null,
            'customip' => $this->customIp,
            'quota' => $this->quotaMb,
            'bwlimit' => $this->bandwidthMb,
            'featurelist' => $this->featureList,
            'owner' => $this->owner,
            'max_email_per_hour' => $this->maxEmailsPerHour,
            'language' => $this->language,
            'hasshell' => $this->shell,
        ];

        // $extra goes first, so it can add parameters but never replace the validated ones.
        return [...$this->extra, ...array_filter($params, static fn (mixed $value): bool => $value !== null && $value !== '')];
    }
}
