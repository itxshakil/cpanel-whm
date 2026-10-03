<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Doctor;

use Carbon\CarbonImmutable;

final readonly class CertificateInfo
{
    public function __construct(
        public bool $trusted,
        public ?CarbonImmutable $validTo = null,
        public ?string $subject = null,
        public ?string $error = null,
    ) {}
}
