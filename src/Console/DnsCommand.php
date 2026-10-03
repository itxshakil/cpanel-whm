<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Console;

use Illuminate\Console\Command;
use Itxshakil\CpanelWhm\Console\Concerns\InteractsWithWhm;
use Itxshakil\CpanelWhm\Data\DnsRecord;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'whm:dns')]
final class DnsCommand extends Command
{
    use InteractsWithWhm;

    protected $signature = 'whm:dns
        {domain? : The zone to show; without it, list every zone}
        {--type= : Only records of this type (A, MX, TXT, ...)}
        {--connection= : The connection to use}';

    protected $description = 'List DNS zones, or the records in one zone';

    public function handle(): int
    {
        $domain = $this->stringArgument('domain');

        try {
            if ($domain === '') {
                $zones = $this->client()->dns()->zones();

                foreach ($zones as $zone) {
                    $this->line($zone);
                }

                $this->components->info("{$zones->count()} zone(s).");

                return self::SUCCESS;
            }

            $zone = $this->client()->dns()->zone($domain);
        } catch (WhmException $whmException) {
            return $this->reportFailure($whmException);
        }

        $type = $this->stringOption('type');
        $records = $type === '' ? $zone->records : $zone->ofType($type);

        $this->table(
            ['Line', 'Name', 'TTL', 'Type', 'Value'],
            $records->map(static fn (DnsRecord $record): array => [
                $record->lineIndex ?? '',
                $record->name,
                $record->ttl ?? '',
                $record->type,
                mb_strimwidth($record->value(), 0, 80, '…'),
            ])->all(),
        );

        $this->components->info("{$records->count()} record(s), serial {$zone->serial}.");

        return self::SUCCESS;
    }
}
