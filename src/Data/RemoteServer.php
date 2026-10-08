<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

use InvalidArgumentException;
use SensitiveParameter;

/**
 * The server accounts are transferred from, for Whm::transfers(). Log in with
 * either a password or an SSH key already stored in WHM. When $user is not
 * root, give $rootPassword and $rootEscalation ("su" or "sudo").
 */
final readonly class RemoteServer
{
    /**
     * @throws InvalidArgumentException when not exactly one login method is given, or the escalation is unknown
     */
    public function __construct(
        public string $host,
        public string $user = 'root',
        #[SensitiveParameter]
        public ?string $password = null,
        public ?string $sshKeyName = null,
        #[SensitiveParameter]
        public ?string $sshKeyPassphrase = null,
        public ?int $port = null,
        #[SensitiveParameter]
        public ?string $rootPassword = null,
        public ?string $rootEscalation = null,
    ) {
        if (($password === null) === ($sshKeyName === null)) {
            throw new InvalidArgumentException('Give the remote server either a password or an sshKeyName, not both and not neither.');
        }

        if ($rootEscalation !== null && ! in_array($rootEscalation, ['su', 'sudo'], true)) {
            throw new InvalidArgumentException("Unknown root escalation [{$rootEscalation}]. Use su or sudo.");
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'host' => $this->host,
            'user' => $this->user,
            'password' => $this->password === null ? null : '[REDACTED]',
            'sshKeyName' => $this->sshKeyName,
            'sshKeyPassphrase' => $this->sshKeyPassphrase === null ? null : '[REDACTED]',
            'port' => $this->port,
            'rootPassword' => $this->rootPassword === null ? null : '[REDACTED]',
            'rootEscalation' => $this->rootEscalation,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toParams(): array
    {
        return [
            'host' => $this->host,
            'user' => $this->user,
            'port' => $this->port,
            'password' => $this->password,
            'sshkey_name' => $this->sshKeyName,
            'sshkey_passphrase' => $this->sshKeyPassphrase,
            'root_password' => $this->rootPassword,
            'root_escalation_method' => $this->rootEscalation,
        ];
    }
}
