<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

use JsonException;

/**
 * One resource record in a DNS zone.
 *
 * The name is what the zone file holds: "www" (relative to the zone),
 * "example.com." (absolute) or "@". Records read from WHM carry their
 * lineIndex, which update() and remove() need.
 *
 *     DnsRecord::a('www', '203.0.113.10');
 *     DnsRecord::mx('@', 10, 'mail.example.com.');
 *     DnsRecord::txt('@', 'v=spf1 include:_spf.example.com ~all');
 */
final readonly class DnsRecord
{
    public const int DEFAULT_TTL = 14400;

    /**
     * @param  list<string>  $data  the record's fields, as WHM splits them (an MX has priority and exchange)
     */
    public function __construct(
        public string $name,
        public string $type,
        public array $data,
        public ?int $ttl = null,
        public ?int $lineIndex = null,
    ) {}

    public static function a(string $name, string $ip, ?int $ttl = null): self
    {
        return new self($name, 'A', [$ip], $ttl);
    }

    public static function aaaa(string $name, string $ip, ?int $ttl = null): self
    {
        return new self($name, 'AAAA', [$ip], $ttl);
    }

    public static function cname(string $name, string $target, ?int $ttl = null): self
    {
        return new self($name, 'CNAME', [$target], $ttl);
    }

    public static function mx(string $name, int $priority, string $exchange, ?int $ttl = null): self
    {
        return new self($name, 'MX', [(string) $priority, $exchange], $ttl);
    }

    public static function ns(string $name, string $host, ?int $ttl = null): self
    {
        return new self($name, 'NS', [$host], $ttl);
    }

    /**
     * Text longer than 255 characters is split into strings of 255, as DNS requires.
     */
    public static function txt(string $name, string $text, ?int $ttl = null): self
    {
        $chunks = $text === '' ? [''] : mb_str_split($text, 255);

        return new self($name, 'TXT', $chunks, $ttl);
    }

    public static function srv(string $name, int $priority, int $weight, int $port, string $target, ?int $ttl = null): self
    {
        return new self($name, 'SRV', [(string) $priority, (string) $weight, (string) $port, $target], $ttl);
    }

    public static function caa(string $name, int $flags, string $tag, string $value, ?int $ttl = null): self
    {
        return new self($name, 'CAA', [(string) $flags, $tag, $value], $ttl);
    }

    /**
     * A row from parse_dns_zone's payload, or null when the row is a comment or
     * a control statement ($TTL, $ORIGIN) rather than a record.
     *
     * @param  array<array-key, mixed>  $row
     */
    public static function fromParsed(array $row): ?self
    {
        if (($row['type'] ?? null) !== 'record') {
            return null;
        }

        $data = [];

        foreach (is_array($row['data_b64'] ?? null) ? $row['data_b64'] : [] as $chunk) {
            $data[] = is_string($chunk) ? (string) base64_decode($chunk, true) : '';
        }

        return new self(
            name: is_string($row['dname_b64'] ?? null) ? (string) base64_decode($row['dname_b64'], true) : '',
            type: strtoupper(Value::string($row['record_type'] ?? null) ?? ''),
            data: $data,
            ttl: Value::int($row['ttl'] ?? null),
            lineIndex: Value::int($row['line_index'] ?? null),
        );
    }

    public function withLineIndex(int $lineIndex): self
    {
        return new self($this->name, $this->type, $this->data, $this->ttl, $lineIndex);
    }

    public function withData(string ...$data): self
    {
        return new self($this->name, $this->type, array_values($data), $this->ttl, $this->lineIndex);
    }

    public function withTtl(int $ttl): self
    {
        return new self($this->name, $this->type, $this->data, $ttl, $this->lineIndex);
    }

    /**
     * The record data as one string: "10 mail.example.com." for an MX. TXT
     * strings are joined without a separator, the way resolvers read them.
     */
    public function value(): string
    {
        return implode($this->type === 'TXT' ? '' : ' ', $this->data);
    }

    /**
     * The fully qualified name, without the trailing dot, for a record in $zone.
     */
    public function fqdn(string $zone): string
    {
        $zone = rtrim(mb_strtolower($zone), '.');
        $name = mb_strtolower($this->name);

        if ($name === '' || $name === '@') {
            return $zone;
        }

        if (str_ends_with($name, '.')) {
            return rtrim($name, '.');
        }

        return $name.'.'.$zone;
    }

    public function is(string $type): bool
    {
        return strcasecmp($this->type, $type) === 0;
    }

    /**
     * The serialized object mass_edit_dns_zone expects in its add and edit lists.
     *
     * @throws JsonException
     */
    public function toMassEditJson(bool $withLineIndex = false): string
    {
        $record = [
            'dname' => $this->name === '' ? '@' : $this->name,
            'ttl' => $this->ttl ?? self::DEFAULT_TTL,
            'record_type' => strtoupper($this->type),
            'data' => $this->data,
        ];

        if ($withLineIndex) {
            $record = ['line_index' => $this->lineIndex, ...$record];
        }

        return json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
