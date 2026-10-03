<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

/**
 * The result of installssl.
 */
final readonly class InstalledCertificate
{
    /**
     * @param  list<string>  $workingDomains  domains the certificate now secures
     * @param  list<string>  $warningDomains  domains on the vhost the certificate does not cover
     */
    public function __construct(
        public string $domain,
        public ?string $user,
        public ?string $ip,
        public array $workingDomains,
        public array $warningDomains,
        public ?string $message,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data, string $domain): self
    {
        return new self(
            domain: Value::string($data['domain'] ?? null) ?? $domain,
            user: Value::string($data['user'] ?? null),
            ip: Value::string($data['ip'] ?? null),
            workingDomains: Value::strings($data['working_domains'] ?? null),
            warningDomains: Value::strings($data['warning_domains'] ?? null),
            message: Value::string($data['message'] ?? null) ?? Value::string($data['statusmsg'] ?? null),
        );
    }
}
