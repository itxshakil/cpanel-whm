<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Fixtures;

use Carbon\CarbonImmutable;
use Itxshakil\CpanelWhm\Doctor\CertificateInfo;
use Itxshakil\CpanelWhm\Doctor\NetworkProbe;
use Itxshakil\CpanelWhm\Doctor\ProbeFailed;

final class FakeProbe implements NetworkProbe
{
    /**
     * @param  list<string>  $addresses
     */
    public function __construct(
        public array $addresses = ['203.0.113.10'],
        public ?string $connectError = null,
        public ?CertificateInfo $certificate = null,
        public ?string $tlsError = null,
    ) {}

    public function resolve(string $host): array
    {
        return $this->addresses;
    }

    public function connect(string $host, int $port, int $timeoutSeconds): float
    {
        if ($this->connectError !== null) {
            throw new ProbeFailed($this->connectError);
        }

        return 38.0;
    }

    public function certificate(string $host, int $port, int $timeoutSeconds): CertificateInfo
    {
        if ($this->tlsError !== null) {
            throw new ProbeFailed($this->tlsError);
        }

        return $this->certificate ?? new CertificateInfo(true, CarbonImmutable::now()->addMonths(3), $host);
    }
}
