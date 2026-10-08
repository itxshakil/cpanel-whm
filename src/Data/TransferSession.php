<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

use Itxshakil\CpanelWhm\WhmResponse;

/**
 * A transfer session WHM created with create_remote_root_transfer_session.
 */
final readonly class TransferSession
{
    public function __construct(
        public string $id,
        public ?string $createOutput,
        public ?string $analyzeOutput,
        public WhmResponse $response,
    ) {}

    public static function fromResponse(WhmResponse $response): self
    {
        return new self(
            id: Value::string($response->get('transfer_session_id')) ?? '',
            createOutput: Value::string($response->get('create_rawout')),
            analyzeOutput: Value::string($response->get('analyze_rawout')),
            response: $response,
        );
    }
}
