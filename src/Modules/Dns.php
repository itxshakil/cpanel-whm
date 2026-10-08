<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Modules;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Itxshakil\CpanelWhm\Data\DnsRecord;
use Itxshakil\CpanelWhm\Data\DnsZone;
use Itxshakil\CpanelWhm\Data\Value;
use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Events\DnsZoneChanged;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\WhmResponse;
use JsonException;

/**
 * DNS zones: listzones, parse_dns_zone, mass_edit_dns_zone, adddns, killdns, resetzone.
 *
 * Record changes go through mass_edit_dns_zone with the zone's current SOA
 * serial, so an edit made from a stale copy of the zone fails instead of
 * overwriting someone else's change.
 *
 *     $zone = Whm::dns()->zone('example.com');
 *     Whm::dns()->add('example.com', DnsRecord::a('www', '203.0.113.10'));
 *     Whm::dns()->update('example.com', $zone->first('www', 'A')->withData('203.0.113.20'));
 *     Whm::dns()->remove('example.com', $zone->first('old', 'CNAME'));
 */
class Dns extends Module
{
    /**
     * The domains that have a zone on the server.
     *
     * @return Collection<int, string>
     *
     * @throws WhmException
     */
    public function zones(): Collection
    {
        $response = $this->client->call('listzones');

        $zones = [];

        foreach ($this->rows($response->get('zone')) as $row) {
            $domain = Value::string($row['domain'] ?? null);

            if ($domain !== null) {
                $zones[] = $domain;
            }
        }

        return collect($zones);
    }

    /**
     * @throws WhmException
     */
    public function zone(string $domain): DnsZone
    {
        $payload = $this->client->call('parse_dns_zone', ['zone' => $domain])->get('payload');

        return DnsZone::fromPayload($domain, is_array($payload) ? $payload : []);
    }

    /**
     * Create a zone for a domain that has none.
     *
     * @throws WhmException
     */
    public function create(string $domain, string $ip, ?string $ipv6 = null, ?string $template = null, ?string $owner = null): WhmResponse
    {
        return $this->client->call('adddns', [
            'domain' => $domain,
            'ip' => $ip,
            'ipv6' => $ipv6,
            'template' => $template,
            'trueowner' => $owner,
        ], HttpMethod::Post);
    }

    /**
     * Delete a zone. This cannot be undone.
     *
     * @throws WhmException
     */
    public function delete(string $domain): WhmResponse
    {
        return $this->client->call('killdns', ['domain' => $domain], HttpMethod::Post);
    }

    /**
     * Put a zone back to the server's default records.
     *
     * @throws WhmException
     */
    public function reset(string $domain): WhmResponse
    {
        return $this->client->call('resetzone', ['domain' => $domain], HttpMethod::Post);
    }

    /**
     * Add records. Returns the zone's new serial.
     *
     * @throws WhmException
     */
    public function add(string $domain, DnsRecord ...$records): ?int
    {
        return $this->edit($domain, add: array_values($records));
    }

    /**
     * Replace records in place. Each record needs the lineIndex it was read with
     * (records from zone() have it). Returns the zone's new serial.
     *
     * @throws WhmException
     */
    public function update(string $domain, DnsRecord ...$records): ?int
    {
        return $this->edit($domain, update: array_values($records));
    }

    /**
     * Remove records, given as records read from zone() or as line indexes.
     * Returns the zone's new serial.
     *
     * @throws WhmException
     */
    public function remove(string $domain, DnsRecord|int ...$records): ?int
    {
        return $this->edit($domain, remove: array_values($records));
    }

    /**
     * Add, update and remove in one request. Without $serial, the zone's
     * current serial is read first.
     *
     * @param  list<DnsRecord>  $add
     * @param  list<DnsRecord>  $update
     * @param  list<DnsRecord|int>  $remove
     *
     * @throws WhmException
     * @throws InvalidArgumentException when an update or removal has no line index
     */
    public function edit(string $domain, array $add = [], array $update = [], array $remove = [], ?int $serial = null): ?int
    {
        if ($add === [] && $update === [] && $remove === []) {
            return $serial;
        }

        // Read the serial uncached: a cached one goes stale after the first edit.
        $serial ??= (new self($this->client->withoutCache()))->zone($domain)->serial;

        if ($serial === null) {
            throw new InvalidArgumentException("The zone {$domain} has no SOA serial; pass one to edit().");
        }

        try {
            $response = $this->client->call('mass_edit_dns_zone', [
                'zone' => $domain,
                'serial' => $serial,
                'add' => array_map(static fn (DnsRecord $record): string => $record->toMassEditJson(), $add),
                'edit' => array_map(static fn (DnsRecord $record): string => self::requireLine($record)->toMassEditJson(true), $update),
                'remove' => array_map(static fn (DnsRecord|int $record): int => is_int($record) ? $record : (int) self::requireLine($record)->lineIndex, $remove),
            ], HttpMethod::Post);
        } catch (JsonException $jsonException) {
            throw new InvalidArgumentException('A DNS record could not be encoded: '.$jsonException->getMessage(), 0, $jsonException);
        }

        $newSerial = Value::int($response->get('new_serial'));

        $this->client->dispatch(new DnsZoneChanged($this->client->config()->name, $domain, $newSerial, count($add), count($update), count($remove)));

        return $newSerial;
    }

    private static function requireLine(DnsRecord $record): DnsRecord
    {
        if ($record->lineIndex === null) {
            throw new InvalidArgumentException("The {$record->type} record for {$record->name} has no line index. Read it from Whm::dns()->zone() first.");
        }

        return $record;
    }
}
