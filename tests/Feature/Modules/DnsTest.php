<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Feature\Modules;

use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Itxshakil\CpanelWhm\Data\DnsRecord;
use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Events\DnsZoneChanged;
use Itxshakil\CpanelWhm\Facades\Whm;
use Itxshakil\CpanelWhm\Tests\TestCase;
use Itxshakil\CpanelWhm\WhmRequest;
use PHPUnit\Framework\Attributes\Test;

final class DnsTest extends TestCase
{
    #[Test]
    public function zones_are_listed(): void
    {
        Whm::fake(['listzones' => Whm::response(['zone' => [
            ['domain' => 'example.com', 'zonefile' => 'example.com.db'],
            ['domain' => '', 'zonefile' => 'broken.db'],
            ['domain' => 'acme.test', 'zonefile' => 'acme.test.db'],
        ]])]);

        self::assertSame(['example.com', 'acme.test'], Whm::dns()->zones()->all());
    }

    #[Test]
    public function a_zone_is_read_with_its_serial(): void
    {
        $fake = Whm::fake(['parse_dns_zone' => Whm::response(['payload' => self::payload()])]);

        $zone = Whm::dns()->zone('example.com');

        self::assertSame(2026100301, $zone->serial);
        self::assertSame('203.0.113.10', $zone->first('www', 'A')?->value());
        $fake->assertCalled('parse_dns_zone', static fn (array $p): bool => $p === ['zone' => 'example.com']);
    }

    #[Test]
    public function records_are_added_with_the_current_serial(): void
    {
        Event::fake([DnsZoneChanged::class]);
        $fake = Whm::fake([
            'parse_dns_zone' => Whm::response(['payload' => self::payload()]),
            'mass_edit_dns_zone' => Whm::response(['new_serial' => 2026100302]),
        ]);

        $serial = Whm::dns()->add('example.com', DnsRecord::a('shop', '203.0.113.20'), DnsRecord::txt('@', 'hello'));

        self::assertSame(2026100302, $serial);
        $fake->assertCalled('mass_edit_dns_zone', static fn (array $p, WhmRequest $r): bool => $r->method === HttpMethod::Post
            && $p['zone'] === 'example.com'
            && $p['serial'] === 2026100301
            && $p['add'] === '{"dname":"shop","ttl":14400,"record_type":"A","data":["203.0.113.20"]}'
            && $p['add-1'] === '{"dname":"@","ttl":14400,"record_type":"TXT","data":["hello"]}'
            && ! isset($p['edit'], $p['remove']));
        Event::assertDispatched(DnsZoneChanged::class, static fn (DnsZoneChanged $e): bool => $e->zone === 'example.com' && $e->serial === 2026100302 && $e->added === 2);
    }

    #[Test]
    public function records_read_from_the_zone_can_be_updated_and_removed(): void
    {
        $fake = Whm::fake([
            'parse_dns_zone' => Whm::response(['payload' => self::payload()]),
            'mass_edit_dns_zone' => Whm::response(['new_serial' => 2026100303]),
        ]);

        $zone = Whm::dns()->zone('example.com');
        $www = $zone->first('www', 'A');
        self::assertInstanceOf(DnsRecord::class, $www);

        Whm::dns()->edit('example.com', update: [$www->withData('198.51.100.7')], remove: [12], serial: $zone->serial);
        Whm::dns()->remove('example.com', $www);

        $fake->assertCalled('mass_edit_dns_zone', static fn (array $p): bool => ($p['edit'] ?? null) === '{"line_index":9,"dname":"www","ttl":14400,"record_type":"A","data":["198.51.100.7"]}'
            && $p['remove'] === 12 && $p['serial'] === 2026100301)
            ->assertCalled('mass_edit_dns_zone', static fn (array $p): bool => $p['remove'] === 9 && ! isset($p['edit']))
            ->assertCalledTimes('parse_dns_zone', 2);
    }

    #[Test]
    public function an_update_needs_a_line_index(): void
    {
        Whm::fake(['*' => Whm::response()]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('has no line index');

        Whm::dns()->edit('example.com', update: [DnsRecord::a('www', '1.2.3.4')], serial: 1);
    }

    #[Test]
    public function an_empty_edit_sends_nothing(): void
    {
        $fake = Whm::fake();

        self::assertNull(Whm::dns()->edit('example.com'));
        $fake->assertNothingSent();
    }

    #[Test]
    public function a_zone_without_soa_cannot_be_edited_safely(): void
    {
        Whm::fake(['parse_dns_zone' => Whm::response(['payload' => []])]);

        $this->expectException(InvalidArgumentException::class);

        Whm::dns()->add('example.com', DnsRecord::a('www', '1.2.3.4'));
    }

    #[Test]
    public function zones_are_created_deleted_and_reset(): void
    {
        $fake = Whm::fake(['*' => Whm::response()]);

        Whm::dns()->create('example.com', '203.0.113.10', owner: 'acme');
        Whm::dns()->delete('old.test');
        Whm::dns()->reset('example.com');

        $fake->assertCalled('adddns', static fn (array $p): bool => $p === ['domain' => 'example.com', 'ip' => '203.0.113.10', 'trueowner' => 'acme'])
            ->assertCalled('killdns', static fn (array $p): bool => $p === ['domain' => 'old.test'])
            ->assertCalled('resetzone', static fn (array $p): bool => $p === ['domain' => 'example.com']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function payload(): array
    {
        $b64 = static fn (string ...$values): array => array_map(base64_encode(...), $values);

        return [
            ['type' => 'record', 'line_index' => 2, 'record_type' => 'SOA', 'ttl' => 86400, 'dname_b64' => base64_encode('example.com.'),
                'data_b64' => $b64('ns1.example.com.', 'root.example.com.', '2026100301', '3600', '1800', '1209600', '86400')],
            ['type' => 'record', 'line_index' => 9, 'record_type' => 'A', 'ttl' => 14400, 'dname_b64' => base64_encode('www'), 'data_b64' => $b64('203.0.113.10')],
        ];
    }
}
