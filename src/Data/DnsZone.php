<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

use Illuminate\Support\Collection;

/**
 * A DNS zone as parse_dns_zone returns it, with the SOA serial that
 * mass_edit_dns_zone needs for a safe edit.
 */
final readonly class DnsZone
{
    /**
     * @param  Collection<int, DnsRecord>  $records
     */
    public function __construct(
        public string $domain,
        public ?int $serial,
        public Collection $records,
    ) {}

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public static function fromPayload(string $domain, array $payload): self
    {
        $records = [];

        foreach ($payload as $row) {
            $record = is_array($row) ? DnsRecord::fromParsed($row) : null;

            if ($record instanceof DnsRecord) {
                $records[] = $record;
            }
        }

        $records = collect($records);
        $soa = $records->first(static fn (DnsRecord $record): bool => $record->is('SOA'));
        $serial = $soa instanceof DnsRecord ? Value::int($soa->data[2] ?? null) : null;

        return new self($domain, $serial, $records);
    }

    /**
     * @return Collection<int, DnsRecord>
     */
    public function ofType(string $type): Collection
    {
        return $this->records->filter(static fn (DnsRecord $record): bool => $record->is($type))->values();
    }

    /**
     * Records with this name ("www", "www.example.com", "@" or the zone itself),
     * optionally of one type.
     *
     * @return Collection<int, DnsRecord>
     */
    public function named(string $name, ?string $type = null): Collection
    {
        $zone = rtrim(mb_strtolower($this->domain), '.');
        $name = rtrim(mb_strtolower($name), '.');
        $wanted = match (true) {
            $name === '' || $name === '@' => $zone,
            $name === $zone || str_ends_with($name, '.'.$zone) => $name,
            default => $name.'.'.$zone,
        };

        return $this->records
            ->filter(fn (DnsRecord $record): bool => $record->fqdn($this->domain) === $wanted && ($type === null || $record->is($type)))
            ->values();
    }

    public function first(string $name, ?string $type = null): ?DnsRecord
    {
        return $this->named($name, $type)->first();
    }
}
