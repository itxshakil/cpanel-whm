<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Modules;

use Itxshakil\CpanelWhm\Data\RemoteServer;
use Itxshakil\CpanelWhm\Data\TransferOptions;
use Itxshakil\CpanelWhm\Data\TransferSession;
use Itxshakil\CpanelWhm\Data\Value;
use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Enums\TransferState;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\Support\UsernameRules;
use Itxshakil\CpanelWhm\WhmResponse;

/**
 * Move accounts from another server with WHM's transfer system, as WHM's
 * Transfer Tool does: create a session to the remote server, enqueue
 * accounts, start it, then poll its state.
 *
 *     $session = Whm::transfers()->migrate(new RemoteServer('old.example.com', password: $password), ['acme', 'shop']);
 *     Whm::transfers()->state($session->id)->isFinished();
 */
class Transfers extends Module
{
    /**
     * Connecting to and analysing the remote server can take minutes.
     */
    public const int SESSION_TIMEOUT = 300;

    /**
     * Connect to the remote server as root (or escalate to it) and analyse it.
     *
     * @throws WhmException
     */
    public function createSession(RemoteServer $server, TransferOptions $options = new TransferOptions): TransferSession
    {
        $response = $this->client->call(
            'create_remote_root_transfer_session',
            [...$options->toParams(), ...$server->toParams()],
            HttpMethod::Post,
            max(self::SESSION_TIMEOUT, $this->client->config()->timeout),
        );

        return TransferSession::fromResponse($response);
    }

    /**
     * Add an account to a session. $localUser is the name it gets here
     * (default: the same); $options takes any other enqueue_transfer_item
     * parameter of the AccountRemoteRoot module, e.g. ['skipbwdata' => true].
     *
     * @param  array<string, mixed>  $options
     *
     * @throws WhmException
     */
    public function enqueueAccount(string $sessionId, string $user, ?string $localUser = null, ?string $domain = null, array $options = []): WhmResponse
    {
        $user = UsernameRules::normalise($user);

        return $this->client->call('enqueue_transfer_item', [
            ...$options,
            'transfer_session_id' => $sessionId,
            'module' => 'AccountRemoteRoot',
            'user' => $user,
            'localuser' => $localUser === null ? $user : UsernameRules::assertValid($localUser),
            'domain' => $domain,
        ], HttpMethod::Post);
    }

    /**
     * Start (or restart) a session. Returns the transfer process id.
     *
     * @throws WhmException
     */
    public function start(string $sessionId): ?int
    {
        return Value::int($this->client->call('start_transfer_session', ['transfer_session_id' => $sessionId], HttpMethod::Post)->get('pid'));
    }

    /**
     * @throws WhmException
     */
    public function state(string $sessionId): TransferState
    {
        $state = Value::string($this->client->call('get_transfer_session_state', ['transfer_session_id' => $sessionId])->get('state_name'));

        return TransferState::tryFrom($state ?? '') ?? TransferState::Unknown;
    }

    /**
     * @throws WhmException
     */
    public function pause(string $sessionId): WhmResponse
    {
        return $this->client->call('pause_transfer_session', ['transfer_session_id' => $sessionId], HttpMethod::Post);
    }

    /**
     * @throws WhmException
     */
    public function abort(string $sessionId): WhmResponse
    {
        return $this->client->call('abort_transfer_session', ['transfer_session_id' => $sessionId], HttpMethod::Post);
    }

    /**
     * Create a session, enqueue every account under the same name, and start it.
     * Poll state($session->id) until isFinished().
     *
     * @param  list<string>  $users
     *
     * @throws WhmException
     */
    public function migrate(RemoteServer $server, array $users, TransferOptions $options = new TransferOptions): TransferSession
    {
        $session = $this->createSession($server, $options);

        foreach ($users as $user) {
            $this->enqueueAccount($session->id, $user);
        }

        $this->start($session->id);

        return $session;
    }
}
