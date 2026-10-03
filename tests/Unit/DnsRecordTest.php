<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Unit;

use Itxshakil\CpanelWhm\Data\DnsRecord;
use Itxshakil\CpanelWhm\Data\DnsZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DnsRecordTest extends TestCase
{
    #[Test]
    public function factories_split_data_the_way_whm_does(): void
    {
        self::assertSame(['10', 'mail.example.com.'], DnsRecord::mx('@', 10, 'mail.example.com.')->data);
        self::assertSame(['0', '5', '5060', 'sip.example.com.'], DnsRecord::srv('_sip._tcp', 0, 5, 5060, 'sip.example.com.')->data);
        self::assertSame(['0', 'issue', 'letsencrypt.org'], DnsRecord::caa('@', 0, 'issue', 'letsencrypt.org')->data);
        self::assertSame('AAAA', DnsRecord::aaaa('www', '2001:db8::1')->type);
        self::assertSame('CNAME', DnsRecord::cname('shop', 'example.com.')->type);
        self::assertSame('NS', DnsRecord::ns('@', 'ns1.example.com.')->type);
    }

    #[Test]
    public function long_txt_values_are_split_into_255_character_strings(): void
    {
        $record = DnsRecord::txt('@', str_repeat('a', 300));

        self::assertSame([255, 45], array_map(strlen(...), $record->data));
        self::assertSame(str_repeat('a', 300), $record->value());
        self::assertSame([''], DnsRecord::txt('@', '')->data);
    }

    #[Test]
    public function names_resolve_against_the_zone(): void
    {
        self::assertSame('www.example.com', DnsRecord::a('www', '1.2.3.4')->fqdn('example.com'));
        self::assertSame('example.com', DnsRecord::a('@', '1.2.3.4')->fqdn('example.com.'));
        self::assertSame('example.com', DnsRecord::a('', '1.2.3.4')->fqdn('example.com'));
        self::assertSame('mail.other.test', DnsRecord::a('mail.other.test.', '1.2.3.4')->fqdn('example.com'));
    }

    #[Test]
    public function records_serialise_for_mass_edit(): void
    {
        $record = DnsRecord::a('www', '203.0.113.10');

        self::assertSame('{"dname":"www","ttl":14400,"record_type":"A","data":["203.0.113.10"]}', $record->toMassEditJson());
        self::assertSame(
            '{"line_index":7,"dname":"@","ttl":300,"record_type":"TXT","data":["v=spf1 -all"]}',
            DnsRecord::txt('', 'v=spf1 -all')->withTtl(300)->withLineIndex(7)->toMassEditJson(true),
        );
        self::assertSame(['198.51.100.1'], $record->withData('198.51.100.1')->data);
    }

    #[Test]
    public function parsed_zones_decode_base64_and_find_the_serial(): void
    {
        $zone = DnsZone::fromPayload('example.com', [
            ['type' => 'comment', 'line_index' => 0, 'text_b64' => base64_encode('; zone')],
            ['type' => 'control', 'line_index' => 1, 'text_b64' => base64_encode('$TTL 14400')],
            ['type' => 'record', 'line_index' => 2, 'record_type' => 'SOA', 'ttl' => 86400, 'dname_b64' => base64_encode('example.com.'),
                'data_b64' => array_map(base64_encode(...), ['ns1.example.com.', 'root.example.com.', '2026100301', '3600', '1800', '1209600', '86400'])],
            ['type' => 'record', 'line_index' => 9, 'record_type' => 'A', 'ttl' => 14400, 'dname_b64' => base64_encode('www'), 'data_b64' => [base64_encode('203.0.113.10')]],
            ['type' => 'record', 'line_index' => 10, 'record_type' => 'mx', 'ttl' => 14400, 'dname_b64' => base64_encode('example.com.'), 'data_b64' => [base64_encode('0'), base64_encode('example.com.')]],
            'not a row',
        ]);

        self::assertSame(2026100301, $zone->serial);
        self::assertCount(3, $zone->records);
        self::assertSame(9, $zone->first('www.example.com', 'A')?->lineIndex);
        self::assertSame('203.0.113.10', $zone->first('www')?->value());
        self::assertCount(1, $zone->ofType('MX'));
        self::assertCount(2, $zone->named('@'));
        self::assertNull($zone->first('ftp'));
    }
}
