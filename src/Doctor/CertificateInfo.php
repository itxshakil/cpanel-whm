<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Doctor;

use Carbon\CarbonImmutable;

final readonly class CertificateInfo
{
    /**
     * @param  string|null  $publicKeyPin  the certificate's public key as a curl pin (sha256//<base64>)
     */
    public function __construct(
        public bool $trusted,
        public ?CarbonImmutable $validTo = null,
        public ?string $subject = null,
        public ?string $error = null,
        public ?string $publicKeyPin = null,
    ) {}
}
