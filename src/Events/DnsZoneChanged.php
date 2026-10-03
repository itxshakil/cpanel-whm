<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Events;

/**
 * Records in a DNS zone were added, edited or removed through Whm::dns().
 */
final readonly class DnsZoneChanged
{
    public function __construct(
        public string $connection,
        public string $zone,
        public ?int $serial,
        public int $added = 0,
        public int $edited = 0,
        public int $removed = 0,
    ) {}
}
