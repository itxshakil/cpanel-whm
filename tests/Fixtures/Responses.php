<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Fixtures;

/**
 * Response bodies shaped like real WHM API 1 output.
 */
final class Responses
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function account(array $overrides = []): array
    {
        return array_replace([
            'user' => 'acme',
            'domain' => 'acme.example',
            'plan' => 'starter',
            'ip' => '203.0.113.20',
            'email' => 'ops@acme.example',
            'owner' => 'root',
            'suspended' => 0,
            'suspendreason' => 'not suspended',
            'diskused' => '120M',
            'disklimit' => '10240M',
            'unix_startdate' => 1767225600,
            'partition' => 'home',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>
     */
    public static function envelope(?array $data = [], int $result = 1, string $reason = 'OK', string $command = 'version'): array
    {
        return [
            'data' => $data,
            'metadata' => ['command' => $command, 'reason' => $reason, 'result' => $result, 'version' => 1],
        ];
    }
}
