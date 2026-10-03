<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Contracts;

use Itxshakil\CpanelWhm\Exceptions\WhmConnectionFailed;
use Itxshakil\CpanelWhm\Support\ConnectionConfig;
use Itxshakil\CpanelWhm\Transport\TransportResponse;
use Itxshakil\CpanelWhm\WhmRequest;

/**
 * Moves a request to WHM and the answer back. The real one is HttpTransport;
 * Whm::fake() swaps in WhmFake. Interpreting the answer is the client's job,
 * so real and faked calls share every parsing and error path.
 */
interface Transport
{
    /**
     * @throws WhmConnectionFailed
     */
    public function send(ConnectionConfig $config, WhmRequest $request): TransportResponse;
}
