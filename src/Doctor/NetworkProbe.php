<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Doctor;

/**
 * The low-level network checks whm:doctor runs before calling the API. Bind
 * your own implementation in tests to simulate failures.
 */
interface NetworkProbe
{
    /**
     * @return list<string> the addresses the host resolves to; empty when it does not resolve
     */
    public function resolve(string $host): array;

    /**
     * Open a TCP connection and return how long it took, in milliseconds.
     *
     * @throws ProbeFailed
     */
    public function connect(string $host, int $port, int $timeoutSeconds): float;

    /**
     * Read the TLS certificate the server presents.
     *
     * @throws ProbeFailed when no TLS handshake is possible at all
     */
    public function certificate(string $host, int $port, int $timeoutSeconds): CertificateInfo;
}
